import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '@/modules/orders/pages/OrderDetailPage'
import { makeOrderDetail, ORDER_ID, PACKAGE_TREE } from '@/modules/orders/pages/orderDetailFixtures'

const COMM_ID = '01JQZ0000000000000COMM01'
const TEMPLATE_ID = '01JQZ0000000000000TMPL01'

/**
 * Template « Client absent ».
 *
 * `CUSTOMER_ABSENT_EMAIL` est un **code de template**, pas une énumération : le
 * §20 interdit d'ajouter `CUSTOMER_ABSENT` aux types, et `templateType` reste
 * `custom`.
 */
const absentTemplate = {
  id: TEMPLATE_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  serviceId: null,
  code: 'CUSTOMER_ABSENT_EMAIL',
  name: 'Client absent',
  channel: 'email',
  templateType: 'custom',
  subjectTemplate: 'Absence lors de notre passage - {{orderNumber}}',
  bodyTemplate: 'Bonjour,\n\nNous sommes passés sans pouvoir vous remettre votre commande.',
  language: 'fr',
  availableVariables: ['orderNumber'],
  isDefault: false,
  isActive: true,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
}

const communication = (overrides: Record<string, unknown> = {}) => ({
  id: COMM_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  orderId: ORDER_ID,
  templateId: TEMPLATE_ID,
  communicationRuleId: null,
  channel: 'email',
  communicationType: 'custom',
  recipientRole: 'delivery_contact',
  recipientName: 'Sophie Bernard',
  recipientEmail: 'sophie@example.test',
  recipientPhone: null,
  subject: 'Absence lors de notre passage - CMD-2026-000001',
  body: 'Bonjour,\n\nNous sommes passés sans pouvoir vous remettre votre commande.',
  status: 'draft',
  scheduledAt: null,
  sentAt: null,
  createdBy: null,
  createdAt: '2026-08-06T10:00:00+00:00',
  updatedAt: '2026-08-06T10:00:00+00:00',
  ...overrides,
})

function renderDetail(permissions: string[], communications: unknown[] = [communication()]) {
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
    http.get(`${API}/communication-templates`, () => HttpResponse.json(paginated([absentTemplate]))),
    // Le detail reflete la ligne : renvoyer un brouillon alors que la liste
    // montre un echec ferait passer un test qui ne prouve rien.
    http.get(`${API}/order-communications/:id`, () =>
      HttpResponse.json({ data: communications[0], meta: [] }),
    ),
    http.get(`${API}/order-communications/:id/attachments`, () =>
      HttpResponse.json({ data: [], meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/communications`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated(communications))
    }),
  )

  renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })

  return calls
}

/** Le tableau des communications, pour distinguer ses libelles de ceux de la commande. */
const table = () => within(screen.getAllByRole('table').at(-1) as HTMLElement)

const openTab = () =>
  screen.findByRole('tab', { name: /^Communications/ }).then((tab) => userEvent.click(tab))

const ALL = [
  'orders.view',
  'order_communications.view',
  'order_communications.create',
  'order_communications.queue',
  'order_communications.cancel',
  'order_communications.retry',
  'order_communications.delete',
]

describe('communications d’une commande', () => {
  it('liste l’historique avec son canal et son statut', async () => {
    renderDetail(['orders.view', 'order_communications.view'])

    await openTab()

    expect(await screen.findByText(/Absence lors de notre passage/)).toBeInTheDocument()
    expect(table().getByText('E-mail')).toBeInTheDocument()
    expect(table().getByText('Brouillon')).toBeInTheDocument()
  })

  it('n’interroge les communications qu’une fois l’onglet ouvert', async () => {
    const calls = renderDetail(['orders.view', 'order_communications.view'])

    await screen.findByRole('tab', { name: /^Communications/ })
    expect(calls).toHaveLength(0)

    await openTab()
    await waitFor(() => expect(calls.length).toBeGreaterThan(0))
  })

  /**
   * Le cas métier du §49, de bout en bout.
   *
   * Aucun bouton « Client absent » n'existe : c'est un template, choisi comme
   * un autre. Le sujet et le corps sont préremplis par le modèle, et la
   * communication part en **brouillon** — il n'y a pas de route `send`.
   */
  it('prépare une communication « Client absent » à partir du modèle', async () => {
    let body: unknown = null
    renderDetail(ALL)

    server.use(
      http.post(`${API}/orders/${ORDER_ID}/communications`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: communication(), meta: [] }, { status: 201 })
      }),
    )

    await openTab()
    await userEvent.click(await screen.findByRole('button', { name: /Nouvelle communication/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByLabelText(/^Modèle/))
    await userEvent.click(await screen.findByRole('option', { name: /Client absent/ }))

    // Le modele preremplit sujet et corps, tels quels.
    expect(dialog.getByLabelText(/^Sujet/)).toHaveValue(
      'Absence lors de notre passage - {{orderNumber}}',
    )
    expect(dialog.getByLabelText(/^Message/)).toHaveValue(absentTemplate.bodyTemplate)

    await userEvent.click(dialog.getByLabelText(/^Destinataire/))
    await userEvent.click(await screen.findByRole('option', { name: 'Contact de livraison' }))
    await userEvent.click(dialog.getByRole('button', { name: /Enregistrer le brouillon/ }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      templateId: TEMPLATE_ID,
      channel: 'email',
      // `custom`, jamais un enum `CUSTOMER_ABSENT` : c'est un code de template.
      communicationType: 'custom',
      recipientRole: 'delivery_contact',
      scheduledAt: null,
    })
    // Une communication manuelle n'a pas de regle : le champ n'est pas envoye.
    expect(body).not.toHaveProperty('communicationRuleId')
  })

  /** Il n'existe pas de route `send` : le verbe est « mettre en file ». */
  it('met un brouillon en file d’envoi, sans prétendre l’envoyer', async () => {
    let queued = false
    renderDetail(ALL)

    server.use(
      http.post(`${API}/order-communications/${COMM_ID}/queue`, () => {
        queued = true
        return HttpResponse.json({ data: communication({ status: 'queued' }), meta: [] })
      }),
    )

    await openTab()
    await userEvent.click(
      await screen.findByRole('button', { name: /Mettre en file d’envoi/ }),
    )

    await waitFor(() => expect(queued).toBe(true))
    expect(screen.queryByRole('button', { name: /^Envoyer$/ })).not.toBeInTheDocument()
  })

  /** Un message parti ne se modifie plus : `allowsContentChanges` s'arrête là. */
  it('n’offre ni mise en file ni suppression sur un message envoyé', async () => {
    renderDetail(ALL, [communication({ status: 'sent', sentAt: '2026-08-06T11:00:00+00:00' })])

    await openTab()
    await screen.findByText(/Absence lors de notre passage/)
    expect(table().getByText('Envoyée')).toBeInTheDocument()

    expect(screen.queryByRole('button', { name: /Mettre en file/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Annuler l’envoi/ })).not.toBeInTheDocument()
  })

  /** Échec : seule la relance est proposée, et son erreur est lisible. */
  it('propose la relance sur un échec et montre l’erreur', async () => {
    renderDetail(
      ALL,
      [communication({ status: 'failed', errorMessage: 'Adresse e-mail refusée par le serveur.' })],
    )

    await openTab()
    expect(await screen.findByRole('button', { name: 'Réessayer' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Mettre en file/ })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Absence lors de notre passage/ }))

    const drawer = within(await screen.findByRole('dialog'))
    expect(await drawer.findByText(/refusée par le serveur/)).toBeInTheDocument()
  })

  it('masque toute action sans les permissions', async () => {
    renderDetail(['orders.view', 'order_communications.view'])

    await openTab()
    await screen.findByText(/Absence lors de notre passage/)

    expect(screen.queryByRole('button', { name: /Nouvelle communication/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Mettre en file/ })).not.toBeInTheDocument()
  })
})
