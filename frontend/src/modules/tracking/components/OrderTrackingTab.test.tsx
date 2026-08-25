import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '@/modules/orders/pages/OrderDetailPage'
import {
  makeOrderDetail,
  ORDER_ID,
  PACKAGE_TREE,
} from '@/modules/orders/pages/orderDetailFixtures'

const event = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ0000000000000TRKE01',
  organizationId: '01JQZ0000000000000000ORG1',
  orderId: ORDER_ID,
  orderServiceId: null,
  tourId: null,
  tourStopId: null,
  eventType: 'depart_entrepot',
  status: 'done',
  description: 'Camion parti du dépôt de Casablanca.',
  latitude: 33.5731,
  longitude: -7.5898,
  occurredAt: '2026-08-05T08:30:00+00:00',
  createdBy: '01JQZ00000000000000USER1',
  creator: {
    id: '01JQZ00000000000000USER1',
    firstName: 'Sophie',
    lastName: 'Bernard',
    email: 'sophie@example.test',
  },
  ...overrides,
})

/**
 * Suivi d'exécution d'une commande.
 *
 * Le journal est en lecture, sauf l'ajout : `tracking-events` n'expose ni
 * `update` ni `destroy`, et le module n'a que `view` et `create`.
 */
const definition = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ0000000000000TDEF01',
  organizationId: '01JQZ0000000000000000ORG1',
  sourceType: 'order_service',
  statusCode: 'in_progress',
  code: 'depart_entrepot',
  title: 'Votre commande est partie',
  description: null,
  icon: null,
  position: 20,
  apiConfigurationId: null,
  isLive: false,
  active: true,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
  ...overrides,
})

function renderDetail(
  permissions: string[],
  events: unknown[] = [event()],
  definitions: unknown[] = [],
) {
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
    http.get(`${API}/tracking-event-definitions`, () => HttpResponse.json(paginated(definitions))),
    http.get(`${API}/orders/${ORDER_ID}/tracking-events`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated(events))
    }),
  )

  renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })

  return calls
}

const openTracking = () =>
  screen.findByRole('tab', { name: /^Suivi/ }).then((tab) => userEvent.click(tab))

describe('suivi d’une commande', () => {
  /** Sans parcours configure, l'ecran retombe sur le journal brut. */
  it('montre le journal brut quand aucun parcours n’est configuré', async () => {
    const calls = renderDetail(['orders.view', 'tracking_events.view'])

    await openTracking()

    expect(await screen.findByText('depart_entrepot')).toBeInTheDocument()
    expect(screen.getByText('Journal des événements')).toBeInTheDocument()

    // Un parcours se lit dans l'ordre ou il se deroule : ascendant.
    await waitFor(() => expect(calls.length).toBeGreaterThan(0))
    expect(calls[0].searchParams.get('sort')).toBe('occurred_at')
    expect(calls[0].searchParams.get('direction')).toBe('asc')
  })

  /** §51 : l'onglet ne charge rien tant qu'il n'est pas ouvert. */
  it('n’interroge le suivi qu’une fois l’onglet ouvert', async () => {
    const calls = renderDetail(['orders.view', 'tracking_events.view'])

    await screen.findByRole('tab', { name: /^Suivi/ })
    expect(calls).toHaveLength(0)

    await openTracking()
    await waitFor(() => expect(calls.length).toBeGreaterThan(0))
  })

  it('ouvre le détail avec ses coordonnées et son auteur', async () => {
    renderDetail(['orders.view', 'tracking_events.view'])

    await openTracking()
    await userEvent.click(await screen.findByRole('button', { name: 'Détail' }))

    const drawer = within(await screen.findByRole('dialog'))
    expect(drawer.getByText('33.5731, -7.5898')).toBeInTheDocument()
    expect(drawer.getByText('Sophie Bernard')).toBeInTheDocument()
    expect(drawer.getByText(/Camion parti du dépôt/)).toBeInTheDocument()
  })

  /** Sans coordonnées, rien n'est affiché — pas un « — » trompeur. */
  it('n’affiche pas de coordonnées quand elles manquent', async () => {
    renderDetail(
      ['orders.view', 'tracking_events.view'],
      [event({ latitude: null, longitude: null })],
    )

    await openTracking()
    await userEvent.click(await screen.findByRole('button', { name: 'Détail' }))

    const drawer = within(await screen.findByRole('dialog'))
    expect(drawer.queryByText('Coordonnées')).not.toBeInTheDocument()
  })

  it('enregistre un événement avec son type et son statut libres', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'tracking_events.view', 'tracking_events.create'])

    server.use(
      http.post(`${API}/tracking-events`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: event(), meta: [] }, { status: 201 })
      }),
    )

    await openTracking()
    await userEvent.click(await screen.findByRole('button', { name: /Ajouter un événement/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(dialog.getByLabelText(/Type d’événement/), 'arrivee_client')
    await userEvent.type(dialog.getByLabelText(/^Statut/), 'done')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      orderId: ORDER_ID,
      eventType: 'arrivee_client',
      status: 'done',
    })
  })

  /** Sans la permission, le journal se lit et rien ne s'y ajoute. */
  it('masque l’ajout sans tracking_events.create', async () => {
    renderDetail(['orders.view', 'tracking_events.view'])

    await openTracking()
    await screen.findByText('depart_entrepot')

    expect(
      screen.queryByRole('button', { name: /Ajouter un événement/ }),
    ).not.toBeInTheDocument()
  })
})

/**
 * Le parcours plutôt que le journal.
 *
 * Toutes les étapes configurées sont montrées dès le début, franchies ou non :
 * une liste qui s'allonge dit où on en est sans jamais dire ce qui reste.
 */
describe('parcours d’une commande', () => {
  it('montre les étapes à venir, pas seulement celles franchies', async () => {
    renderDetail(
      ['orders.view', 'tracking_events.view'],
      [event({ eventType: 'depart_entrepot' })],
      [
        definition({ code: 'depart_entrepot', title: 'Votre commande est partie', position: 10 }),
        definition({
          id: '01JQZ0000000000000TDEF02',
          code: 'livree',
          title: 'Votre commande est livrée',
          position: 30,
        }),
      ],
    )

    await openTracking()

    // Franchie : elle porte sa date. A venir : elle est annoncee quand meme.
    expect(await screen.findByText('Votre commande est partie')).toBeInTheDocument()
    expect(screen.getByText('Votre commande est livrée')).toBeInTheDocument()
    expect(screen.getByText('En attente')).toBeInTheDocument()
  })

  /** L'étape renseignée par une API se signale, sans dire d'où vient la position. */
  it('signale une étape suivie en direct', async () => {
    renderDetail(
      ['orders.view', 'tracking_events.view'],
      [],
      [definition({ code: 'en_route', title: 'En route', isLive: true })],
    )

    await openTracking()

    expect(await screen.findByText('En route')).toBeInTheDocument()
    expect(screen.getByText('Suivi en direct')).toBeInTheDocument()
  })

  /** Un événement sans étape ne disparaît pas : il est survenu. */
  it('montre à part un événement hors parcours', async () => {
    renderDetail(
      ['orders.view', 'tracking_events.view'],
      [event({ eventType: 'incident_route' })],
      [definition({ code: 'livree', title: 'Livrée' })],
    )

    await openTracking()

    expect(await screen.findByText('Autres événements')).toBeInTheDocument()
    expect(screen.getByText('incident_route')).toBeInTheDocument()
  })
})
