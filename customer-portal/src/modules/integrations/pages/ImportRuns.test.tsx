import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeContext } from '@/test/fixtures'
import { makeOverview } from '@/test/integrationFixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { paginated } from '@/test/stockFixtures'

import type { ImportRun } from '../importRunTypes'

const run: ImportRun = {
  id: '01JQZ0000000000000000RUN01',
  configuration: { id: '01JQZ00000000000000IMPORT1', name: 'Commandes Excel' },
  channel: 'portal',
  source: 'excel_import',
  status: 'completed_with_errors',
  fileName: 'commandes.xlsx',
  counts: { rows: 3, orders: 2, created: 1, updated: 0, skipped: 0, invalid: 1, failed: 0 },
  errorMessage: null,
  triggeredBy: { id: 'u1', name: 'Alice Martin' },
  startedAt: '2026-10-10T08:00:00Z',
  finishedAt: '2026-10-10T08:00:05Z',
}

beforeEach(() => {
  server.use(http.get(`${API}/integrations`, () => HttpResponse.json({ data: makeOverview(), meta: {} })))
})

describe('import history', () => {
  it('lists the imports and opens the refused orders', async () => {
    server.use(
      http.get(`${API}/integrations/import-runs`, () => HttpResponse.json(paginated([run]))),
      http.get(`${API}/integrations/import-runs/${run.id}`, () => HttpResponse.json({
        data: { ...run, errors: [{ index: 1, reference: 'XL-1002', status: 'invalid', errors: { 'orders.1.lines.0.quantity': ['Le champ quantité est obligatoire.'] } }] },
        meta: {},
      })),
    )
    renderApp('/integrations', makeContext(['customer.integrations.view', 'customer.integrations.logs.view']))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('tab', { name: 'Imports' }))
    expect(await screen.findByText('commandes.xlsx')).toBeInTheDocument()
    // Une fois dans le filtre, une fois sur la ligne.
    expect(screen.getAllByText('Terminé avec erreurs')).toHaveLength(2)

    await user.click(screen.getByRole('button', { name: 'Détail' }))

    expect(await screen.findByText('Le champ quantité est obligatoire.')).toBeInTheDocument()
    expect(screen.getByText(/Commande n° 2 — XL-1002/)).toBeInTheDocument()
    expect(screen.getByText('Déposé par Alice Martin')).toBeInTheDocument()
  })

  it('hides the history without the permission to read the logs', async () => {
    renderApp('/integrations', makeContext(['customer.integrations.view']))

    expect(await screen.findByRole('tab', { name: 'Flux' })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Imports' })).not.toBeInTheDocument()
  })
})
