import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { TourListPage } from './TourListPage'

const TOUR_ID = '01JQZ0000000000000TOUR01'

const tour = (overrides: Record<string, unknown> = {}) => ({
  id: TOUR_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  tourNumber: 'TR-001',
  tourDate: '2026-09-01',
  agencyId: '01JQZ0000000000000AGEN01',
  depotId: null,
  providerId: null,
  vehicleId: null,
  driverId: null,
  tourType: null,
  instructions: null,
  totalWeight: 120.5,
  totalVolume: 2.5,
  totalPackages: 3,
  totalCustomers: 2,
  drivingTimeMinutes: 0,
  workingTimeMinutes: 0,
  distanceMeters: 0,
  status: 'draft',
  stopCount: 2,
  stops: [
    {
      id: '01JQZ0000000000000STOP01',
      tourId: TOUR_ID,
      addressId: '01JQZ0000000000000ADDR01',
      sequence: 1,
      status: 'pending',
      addressLabel: 'Entrepôt · 20000 Casablanca',
      serviceCount: 3,
      latitude: 33.59,
      longitude: -7.62,
      orderServiceIds: ['01JQZ000000000000OSRV01', '01JQZ000000000000OSRV02'],
      orders: [{ id: '01JQZ00000000000000ORD1', orderNumber: 'CMD-100', serviceCount: 2 }],
    },
    {
      id: '01JQZ0000000000000STOP02',
      tourId: TOUR_ID,
      addressId: '01JQZ0000000000000ADDR02',
      sequence: 2,
      status: 'pending',
      addressLabel: 'Client · 20100 Casablanca',
      serviceCount: 1,
      latitude: 33.55,
      longitude: -7.6,
      orderServiceIds: ['01JQZ000000000000OSRV03'],
      orders: [{ id: '01JQZ00000000000000ORD2', orderNumber: 'CMD-200', serviceCount: 1 }],
    },
  ],
  ...overrides,
})

const ORDER_ID = '01JQZ00000000000000ORDR1'

const poolOrder = {
  id: ORDER_ID,
  orderNumber: 'CMD-9001',
  customerId: '01JQZ0000000000000CUSTO1',
  customerName: 'Atlas Distribution',
  status: 'ready',
  earliestRequestedDate: '2026-09-01',
  serviceCount: 1,
  addressCount: 1,
  totalWeight: 12,
  totalVolume: 0.5,
  totalPackages: 2,
  services: [
    {
      id: '01JQZ000000000000000SVC1',
      serviceNumber: 'S-1',
      serviceCode: 'DEL',
      serviceName: 'Livraison',
      status: 'pending',
      addressId: '01JQZ0000000000000ADDR03',
      addressLabel: 'Client · 20100 Casablanca',
      latitude: 33.57,
      longitude: -7.59,
      requestedDate: '2026-09-01',
      requestedFrom: null,
      requestedTo: null,
      weight: 12,
      volume: 0.5,
      packageCount: 2,
    },
  ],
}

function render(overrides: Record<string, unknown> = {}) {
  const calls: URL[] = []
  const plans: { url: string; body: Record<string, unknown> }[] = []
  const unplans: { url: string; body: Record<string, unknown> }[] = []

  server.use(
    http.get(`${API}/statuses`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/planning/pool`, () => HttpResponse.json(paginated([poolOrder]))),
    http.post(`${API}/tours/:id/unplan`, async ({ request, params }) => {
      unplans.push({
        url: String(params.id),
        body: (await request.json()) as Record<string, unknown>,
      })

      return HttpResponse.json({ data: { unplanned: ['x'], rejected: [] } })
    }),
    http.post(`${API}/tours/:id/plan`, async ({ request, params }) => {
      plans.push({
        url: String(params.id),
        body: (await request.json()) as Record<string, unknown>,
      })

      return HttpResponse.json({ data: { planned: ['x'], rejected: [] } })
    }),
    http.get(`${API}/tours`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated([tour(overrides)]))
    }),
  )

  renderWithProviders(<TourListPage />, { membership: withPermissions(['tours.view']) })

  return { calls, plans, unplans }
}

/** Glisse une charge de planification sur une colonne, comme le navigateur le ferait. */
function dragOnto(column: HTMLElement, payload: Record<string, unknown>) {
  const data = new Map<string, string>([
    ['application/x-tricolis-planning', JSON.stringify(payload)],
  ])

  const dataTransfer = {
    types: [...data.keys()],
    getData: (type: string) => data.get(type) ?? '',
    setData: (type: string, value: string) => data.set(type, value),
    dropEffect: '',
    effectAllowed: '',
  }

  fireEvent.dragOver(column, { dataTransfer })
  fireEvent.drop(column, { dataTransfer })
}

/**
 * Deux lectures d'une même liste : les colonnes pour comparer les tournées
 * entre elles, le tableau pour en retrouver une.
 */
describe('liste des tournées', () => {
  it('ouvre en colonnes, avec les arrêts dans l’ordre', async () => {
    render()

    expect(await screen.findByText('TR-001')).toBeInTheDocument()
    expect(screen.getByText(/Entrepôt/)).toBeInTheDocument()
    // « Clients » est aussi un libelle de metrique : on vise l'adresse.
    expect(screen.getByText(/Client · 20100/)).toBeInTheDocument()
    expect(screen.getByText('3 services')).toBeInTheDocument()
  })

  /**
   * Les arrêts ne sont demandés que par la vue qui les montre : les charger
   * toujours coûterait une jointure à qui ne veut qu'un tableau.
   */
  it('ne demande les arrêts que pour la vue en colonnes', async () => {
    const { calls } = render()

    await screen.findByText('TR-001')
    await waitFor(() => expect(calls[0].searchParams.get('withStops')).toBe('1'))

    await userEvent.click(screen.getByRole('button', { name: 'Vue en tableau' }))

    await waitFor(() => {
      const last = calls.at(-1)
      expect(last?.searchParams.get('withStops')).toBe('0')
    })
  })

  it('bascule vers le tableau et retrouve la tournée', async () => {
    render()

    await screen.findByText('TR-001')
    await userEvent.click(screen.getByRole('button', { name: 'Vue en tableau' }))

    expect(await screen.findByRole('table')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'TR-001' })).toHaveAttribute('href', `/tours/${TOUR_ID}`)
  })

  it('cherche côté serveur', async () => {
    const { calls } = render()

    await screen.findByText('TR-001')
    await userEvent.type(screen.getByLabelText('Rechercher'), 'TR-001')

    await waitFor(() =>
      expect(calls.some((url) => url.searchParams.get('search') === 'TR-001')).toBe(true),
    )
  })

  /** Une distance à zéro n'est pas une mesure : c'est un calcul qui n'a pas eu lieu. */
  it('dit que la distance n’est pas calculée', async () => {
    render()

    expect(await screen.findByText('Non calculé')).toBeInTheDocument()
  })
})

/**
 * Le pool tient dans un panneau, à côté des colonnes : on garde d'un côté ce qui
 * attend, de l'autre ce qui peut le prendre.
 */
describe('planification depuis la liste', () => {
  it('montre le pool à côté des colonnes, et le replie', async () => {
    render()

    expect(await screen.findByText('CMD-9001')).toBeInTheDocument()

    await userEvent.click(
      screen.getByRole('button', { name: 'Replier les commandes à planifier' }),
    )

    expect(screen.queryByText('CMD-9001')).not.toBeInTheDocument()

    await userEvent.click(
      screen.getByRole('button', { name: 'Afficher les commandes à planifier' }),
    )

    expect(await screen.findByText('CMD-9001')).toBeInTheDocument()
  })

  it('planifie la commande déposée sur une tournée', async () => {
    const { plans } = render()

    await screen.findByText('CMD-9001')

    dragOnto(screen.getByTestId(`tour-column-${TOUR_ID}`), {
      kind: 'order',
      id: ORDER_ID,
      label: 'CMD-9001',
    })

    await waitFor(() => expect(plans).toHaveLength(1))
    expect(plans[0]).toEqual({ url: TOUR_ID, body: { orderIds: [ORDER_ID] } })
  })

  it('planifie un service seul quand c’est lui qu’on dépose', async () => {
    const { plans } = render()

    await screen.findByText('CMD-9001')

    dragOnto(screen.getByTestId(`tour-column-${TOUR_ID}`), {
      kind: 'service',
      id: 'svc-42',
      label: 'Livraison',
    })

    await waitFor(() => expect(plans).toHaveLength(1))
    expect(plans[0].body).toEqual({ orderServiceIds: ['svc-42'] })
  })

  /**
   * Une tournée confirmée n'a plus à changer de contenu, et le serveur le
   * refuserait : la colonne ne doit pas laisser croire le contraire.
   */
  it('n’accepte rien sur une tournée qui n’est plus brouillon', async () => {
    const { plans } = render({ status: 'confirmed' })

    await screen.findByText('CMD-9001')

    dragOnto(screen.getByTestId(`tour-column-${TOUR_ID}`), {
      kind: 'order',
      id: ORDER_ID,
      label: 'CMD-9001',
    })

    await new Promise((resolve) => setTimeout(resolve, 50))

    expect(plans).toHaveLength(0)
  })

  /** Le bouton ne choisit pas à la place du planificateur : il n'apparaît que
   *  lorsqu'une seule tournée brouillon peut recevoir. */
  it('propose le bouton quand un seul brouillon existe', async () => {
    const { plans } = render()

    const pool = await screen.findByText('CMD-9001')
    const card = pool.closest('li')

    expect(card).not.toBeNull()

    await userEvent.click(within(card as HTMLElement).getByRole('button', { name: 'Planifier la commande' }))

    await waitFor(() => expect(plans).toHaveLength(1))
    expect(plans[0].body).toEqual({ orderIds: [ORDER_ID] })
  })
})

/**
 * Retirer ce qu'une tournée porte, pour le rendre au pool.
 *
 * Ouvert à tous les états sauf « terminée » : ce qui a été livré n'y retourne
 * pas. Règle posée par le propriétaire du projet le 26 août 2026.
 */
describe('retrait depuis la liste', () => {
  it('rend au pool les services d’un arrêt', async () => {
    const { unplans } = render()

    await screen.findByText('TR-001')

    const buttons = await screen.findAllByRole('button', {
      name: 'Retirer cet arrêt de la tournée',
    })

    await userEvent.click(buttons[0])

    await waitFor(() => expect(unplans).toHaveLength(1))
    expect(unplans[0]).toEqual({
      url: TOUR_ID,
      body: { orderServiceIds: ['01JQZ000000000000OSRV01', '01JQZ000000000000OSRV02'] },
    })
  })

  it('n’offre rien à retirer sur une tournée terminée', async () => {
    render({ status: 'completed' })

    await screen.findByText('TR-001')

    expect(
      screen.queryByRole('button', { name: 'Retirer cet arrêt de la tournée' }),
    ).not.toBeInTheDocument()
  })

  /** Une tournée en route dont un client s'annule doit pouvoir se délester. */
  it('laisse retirer d’une tournée en cours', async () => {
    const { unplans } = render({ status: 'in_progress' })

    await screen.findByText('TR-001')

    const buttons = await screen.findAllByRole('button', {
      name: 'Retirer cet arrêt de la tournée',
    })

    await userEvent.click(buttons[0])

    await waitFor(() => expect(unplans).toHaveLength(1))
  })
})

/**
 * Depuis la colonne, deux façons de vérifier ce qu'une tournée porte : la voir
 * tracée, ou ouvrir la commande qui l'a fait exister.
 */
describe('commandes d’une tournée', () => {
  it('mène à la commande depuis l’arrêt', async () => {
    render()

    expect(await screen.findByRole('link', { name: 'CMD-100' })).toHaveAttribute(
      'href',
      '/orders/01JQZ00000000000000ORD1',
    )
  })

  it('ouvre la carte de la seule tournée choisie', async () => {
    render()

    await screen.findByText('TR-001')
    await userEvent.click(screen.getByRole('button', { name: 'Voir la tournée sur la carte' }))

    const dialog = await screen.findByRole('dialog')

    expect(within(dialog).getByText('Tournée TR-001 sur la carte')).toBeInTheDocument()
    await waitFor(() => expect(document.querySelector('.leaflet-container')).not.toBeNull())
  })

  /** Sans arrêt tracé, la carte n'aurait rien à montrer. */
  it('n’offre pas la carte d’une tournée sans arrêt', async () => {
    render({ stops: [], stopCount: 0 })

    await screen.findByText('TR-001')

    expect(
      screen.queryByRole('button', { name: 'Voir la tournée sur la carte' }),
    ).not.toBeInTheDocument()
  })
})
