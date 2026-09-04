import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { ExportJobListPage } from './ExportJobListPage'

const job = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ00000000000000JOB1',
  customerId: '01JQZ0000000000000CUSTO1',
  configurationId: '01JQZ0000000000000CONF01',
  entityType: 'invoice',
  entityId: '01JQZ00000000000000INV1',
  fileName: 'INV-2026-00042.json',
  hasFile: true,
  status: 'failed',
  attemptCount: 2,
  generatedAt: '2026-08-31T09:00:00+00:00',
  sentAt: null,
  errorMessage: 'Le système du client a répondu 503.',
  configuration: {
    id: '01JQZ0000000000000CONF01',
    name: 'API Migros',
    format: 'json',
    transport: 'rest_api',
  },
  ...overrides,
})

function render(jobs = [job()]) {
  const retries: string[] = []

  server.use(
    http.get(`${API}/customers`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/statuses`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/export-jobs`, () => HttpResponse.json(paginated(jobs))),
    http.post(`${API}/export-jobs/:id/retry`, ({ params }) => {
      retries.push(String(params.id))

      return HttpResponse.json({ data: job({ status: 'pending', errorMessage: null }) })
    }),
  )

  renderWithProviders(<ExportJobListPage />, {
    membership: withPermissions(['export_jobs.view', 'export_jobs.retry', 'customers.view']),
  })

  return { retries }
}

describe('historique des envois', () => {
  /**
   * §27 : un envoi manqué n'annule pas la clôture. Ce que l'exploitant vient
   * chercher ici, c'est la raison de l'échec.
   */
  it('montre l’échec, son message et ses tentatives', async () => {
    render()

    expect(await screen.findByText('INV-2026-00042.json')).toBeInTheDocument()
    expect(screen.getByText(/répondu 503/)).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()
  })

  it('relance un envoi manqué', async () => {
    const { retries } = render()

    await userEvent.click(await screen.findByRole('button', { name: 'Relancer l’envoi' }))

    await waitFor(() => expect(retries).toEqual(['01JQZ00000000000000JOB1']))
  })

  /**
   * Un envoi déjà reçu ne se rejoue pas : le client aurait deux fois la même
   * facture, et le serveur refuserait en 409. Le bouton disparaît plutôt que
   * de promettre une action impossible.
   */
  it('ne propose pas de relancer un envoi déjà transmis', async () => {
    render([job({ status: 'sent', sentAt: '2026-08-31T09:05:00+00:00', errorMessage: null })])

    await screen.findByText('INV-2026-00042.json')

    expect(screen.queryByRole('button', { name: 'Relancer l’envoi' })).not.toBeInTheDocument()
  })
})
