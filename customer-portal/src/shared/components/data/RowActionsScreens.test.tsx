import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext, makeUser } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { makeBalance, makeStockItem, paginated, stockFilterOptions } from '@/test/stockFixtures'

/**
 * Les actions de ligne de la spec (lot 3), en icônes comme au back-office :
 * commandes (voir, suivre, modifier, dupliquer, annuler), stock (voir,
 * modifier, demander une correction), utilisateurs (voir, modifier,
 * désactiver) — chacune selon les capacités du serveur et la permission.
 */
const status = { code: 'draft', label: 'Brouillon', icon: null }
const order = (actions: Record<string, boolean>) => ({
  id: '01ORDER', reference: 'IKEA-2026-000001', customerReference: null, externalReference: null, orderDate: '2026-09-28T10:00:00Z', firstRequestedDate: null,
  status, source: 'customer_portal', servicesCount: 1, packageCount: 0, weight: null, createdAt: null,
  actions: { canView: true, canEdit: true, canDuplicate: true, canCancel: true, canTrack: true, ...actions },
})
const ORDERS = [...ADMIN_PERMISSIONS, 'customer.orders.view', 'customer.orders.update', 'customer.orders.duplicate', 'customer.orders.cancel', 'customer.tracking.view']

const serveOrders = (row: ReturnType<typeof order>) =>
  server.use(
    http.get(`${API}/orders`, () => HttpResponse.json(paginated([row]))),
    http.get(`${API}/order-form-options`, () => HttpResponse.json({ data: { statuses: [], services: [] }, meta: {} })),
  )

describe('row actions per screen', () => {
  it('orders: track, edit, duplicate and cancel from the row, as the backend allows', async () => {
    serveOrders(order({}))
    renderApp('/orders', makeContext(ORDERS))

    expect(await screen.findByRole('link', { name: 'Suivre' })).toHaveAttribute('href', '/orders/01ORDER/tracking')
    expect(screen.getByRole('link', { name: 'Modifier' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Dupliquer' })).toBeInTheDocument()

    await userEvent.setup().click(screen.getByRole('button', { name: 'Annuler la commande' }))
    expect(await screen.findByRole('dialog')).toBeInTheDocument()
  })

  it('orders: hides what the backend refuses', async () => {
    serveOrders(order({ canTrack: false, canDuplicate: false, canCancel: false }))
    renderApp('/orders', makeContext(ORDERS))

    expect(await screen.findByRole('link', { name: 'Consulter' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Suivre' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Dupliquer' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Annuler la commande' })).not.toBeInTheDocument()
  })

  it('stock: edit and request a correction from the row', async () => {
    const item = makeStockItem({ actions: { canView: true, canEditMetadata: true, canViewMovements: false, canViewReservations: false, canRequestChange: true } })
    server.use(
      http.get(`${API}/stock/summary`, () => HttpResponse.json({ data: { itemsCount: 1, quantity: '1', reservedQuantity: '0', availableQuantity: '1' }, meta: {} })),
      http.get(`${API}/stock/items`, () => HttpResponse.json(paginated([item], { filters: stockFilterOptions }))),
      http.get(`${API}/stock/items/01ITEM/balances`, () => HttpResponse.json({ data: [makeBalance()], meta: {} })),
    )
    renderApp('/stock', makeContext(['customer.stock.view', 'customer.stock.items.update', 'customer.stock.adjustment_requests.create']))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Demander une correction' }))
    expect(await screen.findByRole('dialog', { name: /Demander une correction — ART-001/ })).toBeInTheDocument()
    await user.keyboard('{Escape}')

    await user.click(screen.getByRole('button', { name: 'Modifier' }))
    expect(await screen.findByRole('dialog')).toBeInTheDocument()
  })

  it('users: edit and deactivate a member from the row, never the owner', async () => {
    let disabled = false
    server.use(
      http.get(`${API}/users`, () => HttpResponse.json(paginated([makeUser(), makeUser({ id: '01OWNER', isOwner: true, user: { ...makeUser().user, email: 'admin@ikea.test' } })]))),
      http.post(`${API}/users/01VIEWER/disable`, () => {
        disabled = true
        return HttpResponse.json({ data: makeUser({ status: 'disabled' }), meta: {} })
      }),
    )
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true)
    renderApp('/users', makeContext(ADMIN_PERMISSIONS))
    const user = userEvent.setup()

    const viewerRow = (await screen.findByText('viewer@ikea.test')).closest('tr') as HTMLElement
    const ownerRow = screen.getByText('admin@ikea.test').closest('tr') as HTMLElement
    expect(within(ownerRow).queryByRole('button', { name: 'Désactiver' })).not.toBeInTheDocument()
    expect(within(viewerRow).getByRole('button', { name: 'Modifier' })).toBeInTheDocument()

    await user.click(within(viewerRow).getByRole('button', { name: 'Désactiver' }))

    await waitFor(() => expect(disabled).toBe(true))
    confirm.mockRestore()
  })
})
