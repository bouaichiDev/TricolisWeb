import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeContext } from '@/test/fixtures'
import { makeExportJob, makeOverview, makePreview } from '@/test/integrationFixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { paginated } from '@/test/stockFixtures'

const ALL = ['customer.integrations.view', 'customer.integrations.logs.view', 'customer.integrations.import', 'customer.integrations.export_jobs.retry']

beforeEach(() => {
  server.use(http.get(`${API}/integrations`, () => HttpResponse.json({ data: makeOverview(), meta: {} })))
})

describe('integrations page', () => {
  it('lists the customer flows, with an upload action only on manual imports', async () => {
    renderApp('/integrations', makeContext(ALL))

    expect(await screen.findByText('Commandes Excel')).toBeInTheDocument()
    expect(screen.getByText('SFTP (relève automatique)')).toBeInTheDocument()
    expect(screen.getByText('Statuts de commande')).toBeInTheDocument()
    expect(screen.getByText('Restreintes')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: /Importer un fichier/ })).toHaveLength(1)
  })

  it('previews a file, shows the errors, then imports the valid orders', async () => {
    let executed: Record<string, unknown> | null = null
    server.use(
      http.post(`${API}/integrations/imports/preview`, () => HttpResponse.json({ data: makePreview(), meta: {} })),
      http.post(`${API}/integrations/imports/execute`, async ({ request }) => {
        executed = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({
          data: {
            status: 'completed', created: 1, updated: 0, skipped: 0, invalid: 1, failed: 0,
            outcomes: [{ index: 0, reference: 'XL-1001', status: 'created', orderId: '01ORDER', orderNumber: 'QOQA-2026-0007', errors: {} }],
            errors: [{ index: 1, reference: 'XL-1002', status: 'invalid', orderId: null, orderNumber: null, errors: { 'orders.1.lines.0.quantity': ['Le champ quantité est obligatoire.'] } }],
          },
          meta: {},
        })
      }),
    )
    renderApp('/integrations', makeContext(ALL))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Importer un fichier/ }))
    const dialog = await screen.findByRole('dialog')
    await user.upload(within(dialog).getByLabelText('Fichier'), new File(['xlsx'], 'commandes.xlsx'))
    await user.click(within(dialog).getByRole('button', { name: 'Prévisualiser' }))

    expect(await within(dialog).findByText('lines.0.quantity : Le champ quantité est obligatoire.')).toBeInTheDocument()
    expect(within(dialog).getByText('valeur fixe « DELIVERY »')).toBeInTheDocument()

    await user.click(within(dialog).getByRole('button', { name: 'Importer 1 commande(s)' }))

    expect(await within(dialog).findByRole('link', { name: 'QOQA-2026-0007' })).toBeInTheDocument()
    expect(within(dialog).getByText('XL-1002 — Refusée')).toBeInTheDocument()
    expect(executed).toEqual({ configurationId: '01IMPORT', token: 'T'.repeat(48) })
  })

  it('shows the backend refusal of an unreadable file', async () => {
    server.use(http.post(`${API}/integrations/imports/preview`, () => HttpResponse.json({ message: 'Ce fichier n’est pas au format attendu par cette intégration (xlsx).' }, { status: 422 })))
    renderApp('/integrations', makeContext(ALL))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Importer un fichier/ }))
    const dialog = await screen.findByRole('dialog')
    await user.upload(within(dialog).getByLabelText('Fichier'), new File(['x'], 'faux.xlsx'))
    await user.click(within(dialog).getByRole('button', { name: 'Prévisualiser' }))

    expect(await within(dialog).findByText(/pas au format attendu/)).toBeInTheDocument()
  })

  it('lists the sends and retries a failed one', async () => {
    let retried = false
    server.use(
      http.get(`${API}/integrations/export-jobs`, () => HttpResponse.json(paginated([makeExportJob()]))),
      http.post(`${API}/integrations/export-jobs/01JOB/retry`, () => {
        retried = true
        return HttpResponse.json({ data: makeExportJob({ status: 'sent', attemptCount: 2, actions: { canRetry: false } }), meta: {} })
      }),
    )
    renderApp('/integrations', makeContext(ALL))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('tab', { name: /Envois \(1 en échec\)/ }))
    expect(await screen.findByText('Le système du client a répondu 503.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: /Relancer/ }))

    await waitFor(() => expect(retried).toBe(true))
  })

  it('hides the sends without the logs permission', async () => {
    renderApp('/integrations', makeContext(['customer.integrations.view']))

    expect(await screen.findByText('Commandes Excel')).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: /Envois/ })).not.toBeInTheDocument()
  })
})
