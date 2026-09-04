import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { EntityDocumentsTab } from './EntityDocumentsTab'

const CUSTOMER_ID = '01JQZ0000000000000CUST01'

const document = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ0000000000000DOCU01',
  organizationId: '01JQZ0000000000000000ORG1',
  referenceNumber: null,
  documentType: 'contrat',
  status: 'active',
  fileName: 'contrat-cadre.pdf',
  mimeType: 'application/pdf',
  size: 40960,
  receivedAt: null,
  createdBy: null,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
  ...overrides,
})

function render(permissions: string[], documents: unknown[] = [document()]) {
  const calls: URL[] = []

  server.use(
    http.get(`${API}/documents`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated(documents))
    }),
  )

  renderWithProviders(<EntityDocumentsTab entityType="customer" entityId={CUSTOMER_ID} />, {
    membership: withPermissions(permissions),
  })

  return calls
}

/**
 * Les pièces d'un client se listent depuis que `GET /documents` accepte
 * `entityType` et `entityId`. L'onglet annonçait jusque-là un manque de l'API
 * qui n'existe plus.
 */
describe('documents d’une entité', () => {
  it('liste les pièces rattachées à l’entité', async () => {
    const calls = render(['documents.view'])

    expect(await screen.findByText('contrat-cadre.pdf')).toBeInTheDocument()

    await waitFor(() => expect(calls).not.toHaveLength(0))
    expect(calls[0].searchParams.get('entityType')).toBe('customer')
    expect(calls[0].searchParams.get('entityId')).toBe(CUSTOMER_ID)
  })

  /** Le lien polymorphe part avec le fichier : sans lui, la pièce flotterait. */
  it('rattache la pièce déposée à l’entité', async () => {
    let sent: FormData | null = null
    render(['documents.view', 'documents.upload'], [])

    server.use(
      http.post(`${API}/documents`, async ({ request }) => {
        sent = await request.formData()

        return HttpResponse.json({ data: document(), meta: [] }, { status: 201 })
      }),
    )

    await userEvent.upload(
      await screen.findByLabelText(/^Fichier/),
      new File(['contenu'], 'contrat.pdf', { type: 'application/pdf' }),
    )
    await userEvent.type(screen.getByLabelText(/^Type/), 'contrat')
    await userEvent.click(screen.getByRole('button', { name: /Téléverser/ }))

    await waitFor(() => expect(sent).not.toBeNull())
    const form = sent as unknown as FormData
    expect(form.get('entityType')).toBe('customer')
    expect(form.get('entityId')).toBe(CUSTOMER_ID)
    expect(form.get('documentType')).toBe('contrat')
  })

  it('ne propose pas le dépôt sans la permission', async () => {
    render(['documents.view'])

    await screen.findByText('contrat-cadre.pdf')

    expect(screen.queryByRole('button', { name: /Téléverser/ })).not.toBeInTheDocument()
  })
})
