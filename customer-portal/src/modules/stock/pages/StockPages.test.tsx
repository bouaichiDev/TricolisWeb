import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { makeBalance, makeMovement, makeReservation, makeStockItem, paginated, stockFilterOptions } from '@/test/stockFixtures'

const MANAGER = ['customer.stock.view', 'customer.stock.movements.view', 'customer.stock.reservations.view', 'customer.stock.items.create', 'customer.stock.items.update']
const READER = ['customer.stock.view']

function serveList(rows = [makeStockItem()]) {
  server.use(
    http.get(`${API}/stock/summary`, () => HttpResponse.json({ data: { itemsCount: rows.length, quantity: '120.500', reservedQuantity: '20.000', availableQuantity: '100.500' }, meta: {} })),
    http.get(`${API}/stock/items`, () => HttpResponse.json(paginated(rows, { filters: stockFilterOptions }))),
  )
}

function serveDetail(item = makeStockItem()) {
  server.use(
    http.get(`${API}/stock/items/01ITEM`, () => HttpResponse.json({ data: item, meta: {} })),
    http.get(`${API}/stock/items/01ITEM/balances`, () => HttpResponse.json({ data: [makeBalance(), makeBalance({ id: '01B2', location: { ...makeBalance().location!, id: '01LOCB', locationCode: 'B-02-03' }, quantity: '70.500', availableQuantity: '60.500' })], meta: {} })),
    http.get(`${API}/stock/movements`, () => HttpResponse.json(paginated([makeMovement()]))),
    http.get(`${API}/stock/reservations`, () => HttpResponse.json(paginated([makeReservation()]))),
  )
}

describe('stock pages', () => {
  it('shows the summary computed by the backend and the item list', async () => {
    serveList()
    renderApp('/stock', makeContext(MANAGER))

    const totals = await screen.findByLabelText('Quantités')
    expect(within(totals).getByText('120,5')).toBeInTheDocument()
    expect(within(totals).getByText('100,5')).toBeInTheDocument()
    expect(await screen.findByRole('link', { name: 'ART-001' })).toHaveAttribute('href', '/stock/01ITEM')
    expect(screen.getByRole('navigation', { name: 'Rubriques du stock' })).toBeInTheDocument()
  })

  it('says when there is no stock', async () => {
    serveList([])
    renderApp('/stock', makeContext(MANAGER))

    expect(await screen.findByText('Aucun article en stock.')).toBeInTheDocument()
  })

  it('adds an item without any quantity field', async () => {
    let body: Record<string, unknown> | null = null
    serveList()
    serveDetail(makeStockItem({ stock: { quantity: '0.000', reservedQuantity: '0.000', availableQuantity: '0.000' } }))
    server.use(
      http.get(`${API}/stock/catalog-items`, () => HttpResponse.json({ data: [{ id: '01CAT', articleCode: 'BILLY-80', name: 'Bibliothèque Billy 80 cm', description: null, weight: null, volume: null }], meta: {} })),
      http.post(`${API}/stock/items`, async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: makeStockItem(), meta: {} }, { status: 201 })
      }),
    )
    renderApp('/stock', makeContext(MANAGER))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Ajouter un article/ }))
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).queryByLabelText(/Quantité/)).not.toBeInTheDocument()
    await user.selectOptions(within(dialog).getByLabelText('Article du catalogue (facultatif)'), await within(dialog).findByRole('option', { name: /BILLY-80/ }))
    expect(within(dialog).getByLabelText('Code article')).toHaveValue('BILLY-80')
    await user.click(within(dialog).getByRole('button', { name: 'Ajouter' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({ catalogItemId: '01CAT', articleCode: 'BILLY-80' })
    expect(Object.keys(body!)).not.toContain('quantity')
    expect(await screen.findByRole('heading', { name: 'ART-001' })).toBeInTheDocument()
  })

  it('details an item: totals, locations, movements, reservations and linked order', async () => {
    serveDetail()
    renderApp('/stock/01ITEM', makeContext(MANAGER))

    expect(await screen.findByRole('heading', { name: 'ART-001' })).toBeInTheDocument()
    const locations = await screen.findByRole('table', { name: 'Répartition par emplacement' })
    expect(within(locations).getByText(/A-01-01/)).toBeInTheDocument()
    expect(within(locations).getByText('70,5')).toBeInTheDocument()
    expect(await screen.findByText('inbound')).toBeInTheDocument()
    expect(await screen.findByRole('link', { name: 'IKEA-2026-000001' })).toHaveAttribute('href', '/orders/01ORDER')
    expect(screen.getByRole('button', { name: /Modifier/ })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /correction/i })).not.toBeInTheDocument()
  })

  it('hides the edit button and the sections the backend closes', async () => {
    serveDetail(makeStockItem({ actions: { canView: true, canEditMetadata: false, canViewMovements: false, canViewReservations: false, canRequestChange: false } }))
    renderApp('/stock/01ITEM', makeContext(READER))

    expect(await screen.findByRole('table', { name: 'Répartition par emplacement' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Modifier/ })).not.toBeInTheDocument()
    expect(screen.queryByText('Mouvements')).not.toBeInTheDocument()
  })

  it('presents the item of another customer as not found', async () => {
    server.use(http.get(`${API}/stock/items/01QOQA`, () => HttpResponse.json({ message: 'Introuvable.' }, { status: 404 })))
    renderApp('/stock/01QOQA', makeContext(MANAGER))

    expect(await screen.findByText('Article introuvable')).toBeInTheDocument()
  })

  it('lists the movements and the reservations on their own pages', async () => {
    serveDetail()
    renderApp('/stock/reservations', makeContext(MANAGER))

    expect(await screen.findByRole('link', { name: 'IKEA-2026-000001' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('link', { name: 'Mouvements' }))
    expect(await screen.findByText('Entrée')).toBeInTheDocument()
  })

  it('closes the stock pages without the permission', async () => {
    renderApp('/stock', makeContext(['customer.orders.view']))

    expect(await screen.findByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
  })
})
