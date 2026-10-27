import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { makeBalance, makeStockItem, paginated } from '@/test/stockFixtures'

const PERMISSIONS = ['customer.stock.view', 'customer.stock.adjustment_requests.view', 'customer.stock.adjustment_requests.create']
const item = makeStockItem({ actions: { canView: true, canEditMetadata: false, canViewMovements: false, canViewReservations: false, canRequestChange: true } })

beforeEach(() => {
  server.use(
    http.get(`${API}/stock/items/01ITEM`, () => HttpResponse.json({ data: item, meta: {} })),
    http.get(`${API}/stock/items/01ITEM/balances`, () => HttpResponse.json({ data: [makeBalance()], meta: {} })),
  )
})

describe('stock adjustment requests', () => {
  it('sends a correction request from the item, the displayed quantity read from the backend', async () => {
    let body: Record<string, unknown> | null = null
    server.use(
      http.post(`${API}/stock/adjustment-requests`, async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: { id: '01ADJ' }, meta: {} }, { status: 201 })
      }),
    )
    renderApp('/stock/01ITEM', makeContext(PERMISSIONS))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Demander une correction/ }))
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByTestId('displayed-quantity')).toHaveTextContent('120,5')

    await user.selectOptions(within(dialog).getByLabelText('Emplacement'), await within(dialog).findByRole('option', { name: /A-01-01/ }))
    expect(within(dialog).getByTestId('displayed-quantity')).toHaveTextContent('50')
    await user.type(within(dialog).getByLabelText('Quantité constatée'), '47,5')
    await user.type(within(dialog).getByLabelText('Motif'), 'Inventaire')
    await user.click(within(dialog).getByRole('button', { name: 'Envoyer la demande' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ stockItemId: '01ITEM', stockLocationId: '01LOCA', requestedQuantity: '47.5', reason: 'Inventaire', comment: null })
  })

  it('requires a quantity and a reason', async () => {
    renderApp('/stock/01ITEM', makeContext(PERMISSIONS))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Demander une correction/ }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Envoyer la demande' }))

    expect(await screen.findByText('Indiquez le motif de la demande.')).toBeInTheDocument()
    expect(screen.getByText('Une quantité positive, trois décimales au plus.')).toBeInTheDocument()
  })

  it('lists the requests with the carrier answer', async () => {
    server.use(
      http.get(`${API}/stock/adjustment-requests`, () =>
        HttpResponse.json(paginated([{
          id: '01ADJ', stockItem: { id: '01ITEM', articleCode: 'ART-001', description: null }, location: null,
          displayedQuantity: '120.000', requestedQuantity: '118.000', difference: '-2.000', reason: 'Inventaire', comment: null,
          status: { code: 'rejected', label: 'Refusée', icon: null }, isPending: false, reviewComment: 'Recompté : 120 en stock.',
          reviewedAt: '2026-09-30T10:00:00+02:00', createdAt: '2026-09-29T10:00:00+02:00',
        }])),
      ),
    )
    renderApp('/stock/adjustment-requests', makeContext(PERMISSIONS))

    expect(await screen.findByText('Recompté : 120 en stock.')).toBeInTheDocument()
    expect(screen.getByText('Refusée')).toBeInTheDocument()
    expect(screen.getByText('Tout l’article')).toBeInTheDocument()
  })
})
