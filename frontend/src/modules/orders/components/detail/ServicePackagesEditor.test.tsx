import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '../../pages/OrderDetailPage'
import {
  makeOrderDetail,
  ORDER_ID,
  PACKAGE_ID,
  PACKAGE_TREE,
} from '../../pages/orderDetailFixtures'

const SERVICE_ID = '01JQZ0000000000000SRVC01'
const LINK_ID = '01JQZ0000000000000SVPK01'

/**
 * La liaison service ↔ colis, sur une commande enregistrée.
 *
 * `OrderServicePackage` existe au diagramme et se créait à la création complète
 * d'une commande ; aucune route ni aucun écran ne permettait de l'ajouter ou de
 * la retirer ensuite.
 */
function renderDetail(permissions: string[], links: unknown[] = []) {
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
    http.get(`${API}/orders/${ORDER_ID}/services/:serviceId/packages`, () =>
      HttpResponse.json({ data: links, meta: [] }),
    ),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

const openService = async () => {
  await userEvent.click(await screen.findByRole('tab', { name: /^Services/ }))
  await userEvent.click(await screen.findByRole('button', { name: /Ouvrir le détail/ }))
}

const link = {
  id: LINK_ID,
  orderServiceId: SERVICE_ID,
  packageId: PACKAGE_ID,
  quantity: 1,
  handlingInstructions: null,
  status: 'pending',
}

describe('colis pris en charge par un service', () => {
  it('propose chaque colis de la commande', async () => {
    renderDetail(['orders.view', 'order_services.update'])
    await openService()

    expect(await screen.findByRole('checkbox', { name: 'PAL-1' })).not.toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'CTN-1' })).toBeInTheDocument()
  })

  it('rattache un colis au service', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'order_services.update'])

    server.use(
      http.post(
        `${API}/orders/${ORDER_ID}/services/:serviceId/packages`,
        async ({ request }) => {
          body = await request.json()
          return HttpResponse.json({ data: link, meta: [] }, { status: 201 })
        },
      ),
    )

    await openService()
    await userEvent.click(await screen.findByRole('checkbox', { name: 'PAL-1' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ packageId: PACKAGE_ID })
  })

  it('retire un colis du service', async () => {
    let called = false
    renderDetail(['orders.view', 'order_services.update'], [link])

    server.use(
      http.delete(`${API}/orders/${ORDER_ID}/services/:serviceId/packages/${LINK_ID}`, () => {
        called = true
        return new HttpResponse(null, { status: 204 })
      }),
    )

    await openService()
    await userEvent.click(await screen.findByRole('checkbox', { name: 'PAL-1' }))

    await waitFor(() => expect(called).toBe(true))
  })

  /** Chaque liaison porte sa quantité : un service peut n'en charger qu'une partie. */
  it('corrige la quantité prise en charge', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'order_services.update'], [link])

    server.use(
      http.patch(
        `${API}/orders/${ORDER_ID}/services/:serviceId/packages/${LINK_ID}`,
        async ({ request }) => {
          body = await request.json()
          return HttpResponse.json({ data: link, meta: [] })
        },
      ),
    )

    await openService()

    const quantity = await screen.findByLabelText('Quantité')
    await userEvent.clear(quantity)
    await userEvent.type(quantity, '3')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ quantity: 3, handlingInstructions: null })
  })

  /** Le refus du serveur est rédigé pour être lu : il est affiché tel quel. */
  it('affiche le refus d’un doublon', async () => {
    renderDetail(['orders.view', 'order_services.update'])

    server.use(
      http.post(`${API}/orders/${ORDER_ID}/services/:serviceId/packages`, () =>
        HttpResponse.json(
          { message: 'Ce colis est déjà pris en charge par ce service.', errors: {} },
          { status: 422 },
        ),
      ),
    )

    await openService()
    await userEvent.click(await screen.findByRole('checkbox', { name: 'PAL-1' }))

    expect(await screen.findByText(/déjà pris en charge/)).toBeInTheDocument()
  })

  /** Sans la permission, la liste se lit mais ne se coche pas. */
  it('reste en lecture seule sans la permission', async () => {
    renderDetail(['orders.view'], [link])
    await openService()

    expect(await screen.findByText('PAL-1')).toBeInTheDocument()
    expect(screen.queryByRole('checkbox', { name: 'PAL-1' })).not.toBeInTheDocument()
  })
})
