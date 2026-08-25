import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '@/modules/orders/pages/OrderDetailPage'
import { makeOrderDetail, ORDER_ID, PACKAGE_TREE } from '@/modules/orders/pages/orderDetailFixtures'

const doc = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ0000000000000DOCU01',
  organizationId: '01JQZ0000000000000000ORG1',
  referenceNumber: null,
  documentType: 'pod',
  status: 'active',
  fileName: 'pod-signature.pdf',
  mimeType: 'application/pdf',
  size: 20480,
  receivedAt: '2026-08-05T14:20:00+00:00',
  createdBy: null,
  createdAt: '2026-08-05T14:20:00+00:00',
  updatedAt: '2026-08-05T14:20:00+00:00',
  ...overrides,
})

/**
 * Les preuves de livraison sont des documents du dossier de la commande.
 *
 * Le chauffeur les dépose ; elles ne se saisissent pas au bureau, et elles ne
 * se suppriment pas — `DocumentPolicy::delete()` refuse tout document de type
 * `pod`, quelle que soit la permission.
 */
function renderDetail(permissions: string[], documents: unknown[]) {
  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({ data: makeOrderDetail(), meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/services/:serviceId/packages`, () =>
      HttpResponse.json({ data: [], meta: [] }),
    ),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated(documents))),
  )

  renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

const openDocuments = () =>
  screen.findByRole('tab', { name: /^Documents/ }).then((tab) => userEvent.click(tab))

describe('preuves de livraison', () => {
  it('les montre dans leur section, sans onglet séparé', async () => {
    renderDetail(['orders.view', 'documents.view'], [doc()])

    // L'onglet Preuves n'existe plus : ce sont des documents.
    expect(screen.queryByRole('tab', { name: /^Preuves/ })).not.toBeInTheDocument()

    await openDocuments()

    expect(await screen.findByText('Preuves de livraison')).toBeInTheDocument()
    expect(screen.getAllByText('pod-signature.pdf').length).toBeGreaterThan(0)
  })

  /** Aucun bouton de suppression : le serveur refuserait de toute façon. */
  it('n’offre que le téléchargement, jamais la suppression', async () => {
    renderDetail(['orders.view', 'documents.view', 'documents.delete'], [doc()])

    await openDocuments()
    await screen.findByText('Preuves de livraison')

    expect(screen.getAllByRole('button', { name: /Télécharger/ }).length).toBeGreaterThan(0)
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })

  /** Un document ordinaire n'est pas une preuve : la section reste vide. */
  it('ne retient que les documents de type pod', async () => {
    renderDetail(['orders.view', 'documents.view'], [doc({ documentType: 'invoice' })])

    await openDocuments()

    expect(await screen.findByText(/Aucune preuve de livraison déposée/)).toBeInTheDocument()
  })
})
