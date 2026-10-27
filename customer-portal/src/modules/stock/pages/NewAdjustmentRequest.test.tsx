import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { makeBalance, makeStockItem, paginated } from '@/test/stockFixtures'

const VIEW = ['customer.stock.view', 'customer.stock.adjustment_requests.view']
const CREATE = [...VIEW, 'customer.stock.adjustment_requests.create']
const item = makeStockItem()

beforeEach(() => {
  server.use(
    http.get(`${API}/stock/adjustment-requests`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/stock/items/01ITEM/balances`, () => HttpResponse.json({ data: [makeBalance()], meta: {} })),
  )
})

describe('new correction request from the requests page', () => {
  it('picks the article by a server search, then sends the request for it', async () => {
    const searches: string[] = []
    let body: Record<string, unknown> | null = null
    server.use(
      http.get(`${API}/stock/items`, ({ request }) => {
        searches.push(new URL(request.url).searchParams.get('search') ?? '')
        return HttpResponse.json(paginated([item]))
      }),
      http.post(`${API}/stock/adjustment-requests`, async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: { id: '01ADJ' }, meta: {} }, { status: 201 })
      }),
    )
    renderApp('/stock/adjustment-requests', makeContext(CREATE))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Nouvelle demande' }))
    const picker = await screen.findByRole('dialog')
    await user.type(within(picker).getByLabelText('Rechercher un article'), 'ART')
    await waitFor(() => expect(searches).toContain('ART'))
    // Saisie différée : une requête pour le mot, pas une par lettre.
    expect(searches).not.toContain('A')
    expect(searches).not.toContain('AR')
    await user.click(await within(picker).findByRole('button', { name: /ART-001/ }))

    const dialog = await screen.findByRole('dialog', { name: /Demander une correction — ART-001/ })
    await user.type(within(dialog).getByLabelText('Quantité constatée'), '118')
    await user.type(within(dialog).getByLabelText('Motif'), 'Inventaire annuel')
    await user.click(within(dialog).getByRole('button', { name: 'Envoyer la demande' }))

    await waitFor(() => expect(body).toMatchObject({ stockItemId: '01ITEM', requestedQuantity: '118', reason: 'Inventaire annuel' }))
  })

  it('explains instead of offering a request when the customer has no stock article', async () => {
    server.use(http.get(`${API}/stock/items`, () => HttpResponse.json(paginated([]))))
    renderApp('/stock/adjustment-requests', makeContext(CREATE))

    expect(await screen.findByText(/Aucun article en stock/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Nouvelle demande' })).not.toBeInTheDocument()
  })

  it('offers no new request without the permission to create one', async () => {
    server.use(http.get(`${API}/stock/items`, () => HttpResponse.json(paginated([item]))))
    renderApp('/stock/adjustment-requests', makeContext(VIEW))

    expect(await screen.findByRole('heading', { name: 'Demandes de correction' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Nouvelle demande' })).not.toBeInTheDocument()
  })
})
