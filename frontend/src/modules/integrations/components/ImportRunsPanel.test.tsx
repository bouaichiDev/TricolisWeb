import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import type { ImportRun } from '../types/importRun'
import { ImportRunsPanel } from './ImportRunsPanel'

const ID = '01JQZ00000000000000IMPORT1'

const run: ImportRun = {
  id: '01JQZ0000000000000000RUN01',
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

describe('ImportRunsPanel', () => {
  it('liste les exécutions et déplie les commandes refusées', async () => {
    server.use(
      http.get(`${API}/customer-import-configurations/${ID}/runs`, () =>
        HttpResponse.json({ data: [run], meta: { currentPage: 1, perPage: 10, total: 1, lastPage: 1 }, links: {} })),
      http.get(`${API}/customer-import-configurations/${ID}/runs/${run.id}`, () =>
        HttpResponse.json({ data: { ...run, errors: [{ index: 1, reference: 'XL-1002', status: 'invalid', errors: { 'lines.0.quantity': ['La quantité doit être positive.'] } }] } })),
    )
    renderWithProviders(<ImportRunsPanel configurationId={ID} />, { membership: withPermissions(['customer_import_configurations.view']) })

    expect(await screen.findByText('Terminé avec erreurs')).toBeInTheDocument()
    expect(screen.getByText('commandes.xlsx')).toBeInTheDocument()
    expect(screen.getByText(/1 créée\(s\).*1 refusée\(s\)/)).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { expanded: false }))

    expect(await screen.findByText('La quantité doit être positive.')).toBeInTheDocument()
    expect(screen.getByText(/XL-1002/)).toBeInTheDocument()
    expect(screen.getByText('Lancé par Alice Martin')).toBeInTheDocument()
  })

  it('dit qu’il n’y a encore rien', async () => {
    server.use(http.get(`${API}/customer-import-configurations/${ID}/runs`, () =>
      HttpResponse.json({ data: [], meta: { currentPage: 1, perPage: 10, total: 0, lastPage: 1 }, links: {} })))
    renderWithProviders(<ImportRunsPanel configurationId={ID} />, { membership: withPermissions(['customer_import_configurations.view']) })

    expect(await screen.findByText("Aucune exécution pour l'instant.")).toBeInTheDocument()
  })
})
