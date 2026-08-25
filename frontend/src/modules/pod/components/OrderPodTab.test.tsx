import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '@/modules/orders/pages/OrderDetailPage'
import { makeOrderDetail, ORDER_ID, PACKAGE_TREE } from '@/modules/orders/pages/orderDetailFixtures'

const POD_ID = '01JQZ00000000000000POD01'
const SIGNATURE_ID = '01JQZ0000000000000DOCU01'

const pod = (overrides: Record<string, unknown> = {}) => ({
  id: POD_ID,
  orderId: ORDER_ID,
  orderServiceId: '01JQZ0000000000000SRVC01',
  tourStopId: null,
  recipientName: 'Sophie Bernard',
  signatureDocumentId: SIGNATURE_ID,
  photoDocumentId: null,
  deliveredAt: '2026-08-05T14:20:00+00:00',
  createdBy: null,
  ...overrides,
})

const document = {
  id: SIGNATURE_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  referenceNumber: null,
  documentType: 'signature',
  status: 'active',
  fileName: 'signature-sophie.png',
  mimeType: 'image/png',
  size: 20480,
  receivedAt: null,
  createdBy: null,
  createdAt: '2026-08-05T14:20:00+00:00',
  updatedAt: '2026-08-05T14:20:00+00:00',
}

function renderDetail(permissions: string[], pods: unknown[] = [pod()]) {
  const calls: URL[] = []

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
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/proofs-of-delivery`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated(pods))
    }),
    http.get(`${API}/proofs-of-delivery/${POD_ID}`, () =>
      HttpResponse.json({
        data: { ...pod(), remark: 'Colis remis en main propre.', signatureDocument: document },
        meta: [],
      }),
    ),
  )

  renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })

  return calls
}

const openPod = () =>
  screen.findByRole('tab', { name: /^Preuves/ }).then((tab) => userEvent.click(tab))

describe('preuves de livraison', () => {
  it('liste les preuves avec leur destinataire et leur service', async () => {
    renderDetail(['orders.view', 'proofs_of_delivery.view'])

    await openPod()

    expect(await screen.findByText('Sophie Bernard')).toBeInTheDocument()
    expect(screen.getByText('Livraison standard')).toBeInTheDocument()
    expect(screen.getByText('Signature')).toBeInTheDocument()
  })

  /** §51 : rien n'est chargé tant que l'onglet n'est pas ouvert. */
  it('n’interroge les preuves qu’une fois l’onglet ouvert', async () => {
    const calls = renderDetail(['orders.view', 'proofs_of_delivery.view'])

    await screen.findByRole('tab', { name: /^Preuves/ })
    expect(calls).toHaveLength(0)

    await openPod()
    await waitFor(() => expect(calls.length).toBeGreaterThan(0))
  })

  /**
   * La liste ne porte que les identifiants des documents ; le détail les
   * charge. Le nom du fichier ne peut donc venir que du second appel.
   */
  it('charge la signature à l’ouverture du détail', async () => {
    renderDetail(['orders.view', 'proofs_of_delivery.view'])

    await openPod()
    await userEvent.click(await screen.findByRole('button', { name: 'Détail' }))

    const drawer = within(await screen.findByRole('dialog'))
    expect(await drawer.findByText('signature-sophie.png')).toBeInTheDocument()
    expect(drawer.getByText(/image\/png · 20 Ko/)).toBeInTheDocument()
    expect(drawer.getByText(/main propre/)).toBeInTheDocument()
  })

  /** Photo absente : le dire, plutôt qu'afficher un identifiant vide. */
  it('dit qu’une pièce n’a pas été fournie', async () => {
    renderDetail(['orders.view', 'proofs_of_delivery.view'])

    await openPod()
    await userEvent.click(await screen.findByRole('button', { name: 'Détail' }))

    const drawer = within(await screen.findByRole('dialog'))
    expect(await drawer.findByText('Non fournie.')).toBeInTheDocument()
  })

  it('enregistre une preuve sans exiger de signature', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'proofs_of_delivery.view', 'proofs_of_delivery.create'])

    server.use(
      http.post(`${API}/orders/${ORDER_ID}/proofs-of-delivery`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: pod(), meta: [] }, { status: 201 })
      }),
    )

    await openPod()
    await userEvent.click(await screen.findByRole('button', { name: /Enregistrer une preuve/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(dialog.getByLabelText(/^Destinataire/), 'Karim Alaoui')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({ orderId: ORDER_ID, recipientName: 'Karim Alaoui' })
  })

  /** Ni modification ni suppression : les routes n'existent pas. */
  it('n’offre aucune modification ni suppression', async () => {
    renderDetail(['orders.view', 'proofs_of_delivery.view', 'proofs_of_delivery.create'])

    await openPod()
    await screen.findByText('Sophie Bernard')

    expect(screen.queryByRole('button', { name: /Modifier/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Supprimer/ })).not.toBeInTheDocument()
  })
})
