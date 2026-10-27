import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StockAdjustmentRequestListPage } from './StockAdjustmentRequestListPage'
import { adjustmentRow, serveAdjustmentStatuses } from '../adjustmentTestSupport'
import { CUSTOMER_ID, serveCustomers } from '../testSupport'

function serveList(rows: unknown[] = [adjustmentRow()]) {
  const calls: URL[] = []

  serveAdjustmentStatuses()
  serveCustomers()
  server.use(
    http.get(`${API}/stock-adjustment-requests`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated(rows))
    }),
  )

  return calls
}

const render = () =>
  renderWithProviders(<StockAdjustmentRequestListPage />, {
    membership: withPermissions(['stock_adjustment_requests.view']),
  })

describe('demandes de correction', () => {
  it('liste les demandes avec client, article, quantités, écart et demandeur', async () => {
    serveList()
    render()

    expect(await screen.findByText('PAL-EUR')).toBeInTheDocument()
    expect(screen.getByText('Client Nord')).toBeInTheDocument()
    expect(screen.getByText('50')).toBeInTheDocument()
    expect(screen.getByText('47.5')).toBeInTheDocument()
    expect(screen.getByText('−2.5')).toBeInTheDocument()
    expect(screen.getByText('Ingrid Admin')).toBeInTheDocument()
    expect(screen.getByText(/Dépôt Casablanca · A-01-2-3/)).toBeInTheDocument()
  })

  /** Le libellé vient du référentiel : le code `pending` devient « En attente ». */
  it('affiche le badge de statut lu dans le référentiel', async () => {
    serveList([adjustmentRow(), adjustmentRow({ id: 'x2', status: 'rejected' })])
    render()

    expect(await screen.findByText('En attente')).toBeInTheDocument()
    expect(await screen.findByText('Refusée')).toBeInTheDocument()
  })

  it('présente une demande « tout l’article » sans emplacement', async () => {
    serveList([adjustmentRow({ stockLocationId: null, location: null })])
    render()

    expect(await screen.findByText('Tout l’article')).toBeInTheDocument()
  })

  /** Sans tri choisi, le serveur ordonne « en attente d'abord » : l'écran n'envoie rien. */
  it('laisse le serveur ordonner, puis filtre côté serveur', async () => {
    const calls = serveList()
    render()

    await screen.findByText('PAL-EUR')
    expect(calls[0].searchParams.has('sort')).toBe(false)

    await userEvent.click(screen.getByRole('combobox', { name: 'Client' }))
    await userEvent.click(await screen.findByRole('option', { name: /Client Nord/ }))
    await waitFor(() => expect(calls.at(-1)?.searchParams.get('customerId')).toBe(CUSTOMER_ID))

    await userEvent.type(screen.getByRole('textbox', { name: 'Code article ou motif' }), 'PAL')
    await waitFor(() => expect(calls.at(-1)?.searchParams.get('search')).toBe('PAL'))
  })

  it('envoie le statut en liste, et la période', async () => {
    const calls = serveList()
    const { container } = render()

    await screen.findByText('PAL-EUR')

    await userEvent.click(screen.getByRole('combobox', { name: /statut/i }))
    await userEvent.click(await screen.findByRole('option', { name: 'En attente' }))
    await waitFor(() => expect(calls.at(-1)?.searchParams.getAll('status[]')).toEqual(['pending']))

    const [from] = container.querySelectorAll('input[type="date"]')
    await userEvent.type(from as HTMLInputElement, '2026-09-01')
    await waitFor(() => expect(calls.at(-1)?.searchParams.get('createdFrom')).toBe('2026-09-01'))
  })

  it('mène à la fiche de la demande', async () => {
    serveList()
    render()

    await screen.findByText('PAL-EUR')
    expect(screen.getByRole('link', { name: 'Consulter' })).toHaveAttribute(
      'href',
      `/stock/adjustment-requests/${adjustmentRow().id}`,
    )
  })

  it('affiche proprement un refus d’accès', async () => {
    serveAdjustmentStatuses()
    serveCustomers()
    server.use(
      http.get(`${API}/stock-adjustment-requests`, () =>
        HttpResponse.json({ message: 'Action non autorisée.' }, { status: 403 }),
      ),
    )
    render()

    expect(await screen.findByText('Action non autorisée.')).toBeInTheDocument()
  })
})
