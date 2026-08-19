import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '../../pages/OrderDetailPage'
import {
  CHILD_PACKAGE_ID,
  LINE_ID,
  makeOrderDetail,
  ORDER_ID,
  PACKAGE_ID,
  PACKAGE_TREE,
} from '../../pages/orderDetailFixtures'

/**
 * La relation colis ↔ ligne, sur une commande déjà enregistrée.
 *
 * `PackageOrderLine` existe au diagramme et l'assistant de création la
 * proposait, mais la fiche affichait lignes et colis côte à côte sans permettre
 * de les relier. C'est ce que ces tests couvrent.
 */
function renderDetail(permissions: string[]) {
  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({ data: makeOrderDetail(), meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

/** Ouvre l'onglet Colis et déplie l'un des colis de l'arbre. */
async function openPackage(index = 0) {
  await userEvent.click(await screen.findByRole('tab', { name: 'Colis' }))
  await screen.findAllByText('PAL-1')
  await userEvent.click(screen.getAllByRole('button', { name: /Plus de détails/ })[index])
}

const openFirstPackage = () => openPackage(0)

describe('contenu d’un colis', () => {
  /**
   * Les trois nombres sont ceux que `PackageLineAllocator` fait respecter sous
   * verrou côté serveur.
   */
  it('affiche commandé, affecté et reste pour chaque ligne', async () => {
    renderDetail(['orders.view'])
    await openFirstPackage()

    // 10 commandés, 4 affectés au colis, 6 restants.
    expect(
      await screen.findByText(/Commandé 10 · Affecté 4 · Reste à affecter 6/),
    ).toBeInTheDocument()
  })

  /** La ligne est déjà dans ce colis : c'est la quantité qui change. */
  it('modifie une quantité déjà affectée', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'packages.update'])

    server.use(
      http.patch(
        `${API}/orders/${ORDER_ID}/packages/${PACKAGE_ID}/lines/${LINE_ID}`,
        async ({ request }) => {
          body = await request.json()
          return HttpResponse.json({ data: {}, meta: [] })
        },
      ),
    )

    await openFirstPackage()

    const quantity = await screen.findByLabelText('Quantité affectée')
    await userEvent.clear(quantity)
    await userEvent.type(quantity, '7')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ orderLineId: LINE_ID, quantity: 7 })
  })

  /** Le colis enfant ne transporte encore rien : l'affectation est une création. */
  it('affecte une ligne à un colis qui n’en portait aucune', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'packages.update'])

    server.use(
      http.post(
        `${API}/orders/${ORDER_ID}/packages/${CHILD_PACKAGE_ID}/lines`,
        async ({ request }) => {
          body = await request.json()
          return HttpResponse.json({ data: {}, meta: [] }, { status: 201 })
        },
      ),
    )

    await openPackage(1)

    await userEvent.type(await screen.findByLabelText('Quantité affectée'), '3')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ orderLineId: LINE_ID, quantity: 3 })
  })

  it('retire une ligne du colis', async () => {
    let called = false
    renderDetail(['orders.view', 'packages.update'])

    server.use(
      http.delete(
        `${API}/orders/${ORDER_ID}/packages/${PACKAGE_ID}/lines/${LINE_ID}`,
        () => {
          called = true
          return new HttpResponse(null, { status: 204 })
        },
      ),
    )

    await openFirstPackage()
    await userEvent.click(await screen.findByRole('button', { name: 'Retirer cette ligne du colis' }))

    await waitFor(() => expect(called).toBe(true))
  })

  /** Le refus du serveur est rédigé pour être lu : il est affiché tel quel. */
  it('affiche le dépassement refusé par le serveur', async () => {
    renderDetail(['orders.view', 'packages.update'])

    server.use(
      http.patch(`${API}/orders/${ORDER_ID}/packages/${PACKAGE_ID}/lines/${LINE_ID}`, () =>
        HttpResponse.json(
          {
            message: 'La quantité affectée dépasse la quantité commandée : 14 demandé pour 10.',
            errors: {},
          },
          { status: 422 },
        ),
      ),
    )

    await openFirstPackage()

    const quantity = await screen.findByLabelText('Quantité affectée')
    await userEvent.clear(quantity)
    await userEvent.type(quantity, '14')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByText(/dépasse la quantité commandée/)).toBeInTheDocument()
  })

  /** Sans la permission, le contenu se lit mais ne se modifie pas. */
  it('reste en lecture seule sans la permission', async () => {
    renderDetail(['orders.view'])
    await openFirstPackage()

    expect(screen.queryByLabelText('Quantité affectée')).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Retirer cette ligne du colis' }),
    ).not.toBeInTheDocument()
  })
})
