import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '../../pages/OrderDetailPage'
import {
  LINE_ID,
  makeOrderDetail,
  ORDER_ID,
  PACKAGE_ID,
  PACKAGE_TREE,
} from '../../pages/orderDetailFixtures'
import type { OrderDetail } from '../../types/orderDetail'

const AUDIT = {
  id: '01JQZ0000000000000AUDT01',
  organizationId: '01JQZ0000000000000000ORG1',
  userId: null,
  action: 'updated',
  entityType: 'order_line',
  entityId: LINE_ID,
  oldValues: { quantity: 10 },
  newValues: { quantity: 12 },
  ipAddress: null,
  createdAt: '2026-08-02T09:00:00.000000Z',
}

function renderDetail(permissions: string[], order: Partial<OrderDetail> = {}) {
  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({ data: makeOrderDetail(order), meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([AUDIT]))),
    http.get(`${API}/package-types`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/package-grouping-types`, () => HttpResponse.json(paginated([]))),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

const openTab = async (name: string) =>
  userEvent.click(await screen.findByRole('tab', { name }))

describe('modification du contenu d’une commande', () => {
  it('corrige une ligne existante par sa propre route', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'order_lines.update'])

    server.use(
      http.patch(`${API}/orders/${ORDER_ID}/lines/${LINE_ID}`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: {}, meta: [] })
      }),
    )

    await openTab('Lignes')
    await userEvent.click(await screen.findByRole('button', { name: 'Modifier la ligne' }))

    const quantity = await screen.findByLabelText('Quantité')
    await userEvent.clear(quantity)
    await userEvent.type(quantity, '12')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect((body as { quantity: number }).quantity).toBe(12)
  })

  it('ajoute une ligne à une commande existante', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'order_lines.create'])

    server.use(
      http.post(`${API}/orders/${ORDER_ID}/lines`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: {}, meta: [] }, { status: 201 })
      }),
    )

    await openTab('Lignes')
    await userEvent.click(await screen.findByRole('button', { name: /Ajouter une ligne/ }))
    await userEvent.type(await screen.findByLabelText('Libellé'), 'Film étirable')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect((body as { name: string }).name).toBe('Film étirable')
  })

  it('supprime un colis après confirmation', async () => {
    let called = false
    renderDetail(['orders.view', 'packages.delete'])

    server.use(
      http.delete(`${API}/orders/${ORDER_ID}/packages/${PACKAGE_ID}`, () => {
        called = true
        return new HttpResponse(null, { status: 204 })
      }),
    )

    await openTab('Colis')
    await userEvent.click((await screen.findAllByRole('button', { name: 'Retirer le colis' }))[0])
    await userEvent.click(await screen.findByRole('button', { name: 'Supprimer' }))

    await waitFor(() => expect(called).toBe(true))
  })

  /** Au-delà de `CONFIRMED`, le contenu est engagé auprès de l'exploitation. */
  it('retire les actions de contenu quand la commande est figée', async () => {
    renderDetail(['orders.view', 'order_lines.update', 'order_lines.create', 'packages.delete'], {
      allowsContentChanges: false,
      status: 'planned',
    })

    await openTab('Lignes')
    await screen.findAllByText('Carton renforcé')

    expect(screen.queryByRole('button', { name: 'Modifier la ligne' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Ajouter une ligne/ })).not.toBeInTheDocument()
  })

  /** Sans la permission, l'action ne s'affiche pas même si la commande l'accepte. */
  it('masque la modification sans la permission correspondante', async () => {
    renderDetail(['orders.view'])

    await openTab('Lignes')
    await screen.findAllByText('Carton renforcé')

    expect(screen.queryByRole('button', { name: 'Modifier la ligne' })).not.toBeInTheDocument()
  })
})

describe('historique par élément', () => {
  /**
   * Il n'existe pas de table d'historique : chaque écriture est journalisée
   * dans l'audit avec son type d'entité et son identifiant.
   */
  it('charge l’historique d’une ligne seulement une fois déplié', async () => {
    const queries: URLSearchParams[] = []
    renderDetail(['orders.view', 'audit.view'])

    server.use(
      http.get(`${API}/audit-logs`, ({ request }) => {
        queries.push(new URL(request.url).searchParams)
        return HttpResponse.json(paginated([AUDIT]))
      }),
    )

    await openTab('Lignes')
    await screen.findAllByText('Carton renforcé')

    expect(queries).toHaveLength(0)

    await userEvent.click((await screen.findAllByRole('button', { name: /Voir l’historique/ }))[0])

    await waitFor(() => expect(queries.length).toBeGreaterThan(0))
    expect(queries[0].get('entityType')).toBe('order_line')
    expect(queries[0].get('entityId')).toBe(LINE_ID)

    const entry = await screen.findByText('Modification')
    expect(entry).toBeInTheDocument()
    expect(screen.getByText('10 → 12')).toBeInTheDocument()
  })

  it('explique le manque de permission plutôt que d’afficher un bloc vide', async () => {
    renderDetail(['orders.view'])

    await openTab('Lignes')

    expect(
      (await screen.findAllByText(/demande la permission/i)).length,
    ).toBeGreaterThan(0)
  })
})

describe('statut d’un service', () => {
  /** `UpdateOrderServiceStatusRequest` n'impose aucune machine à états. */
  it('propose les neuf statuts d’un service', async () => {
    renderDetail(['orders.view', 'order_services.change_status'])

    await openTab('Services')
    await userEvent.click(
      await screen.findByRole('button', { name: 'Changer le statut du service' }),
    )
    await userEvent.click(await screen.findByRole('combobox'))

    const listbox = await screen.findByRole('listbox')
    expect(within(listbox).getAllByRole('option')).toHaveLength(9)
  })
})
