import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { PlanningPage } from './PlanningPage'

const ORDER_ID = '01JQZ0000000000000ORDR01'
const SERVICE_ID = '01JQZ0000000000000OSRV01'
const TOUR_ID = '01JQZ0000000000000TOUR01'

const poolOrder = (overrides: Record<string, unknown> = {}) => ({
  id: ORDER_ID,
  orderNumber: 'CMD-42',
  customerId: '01JQZ0000000000000CUST01',
  customerName: 'Meubles Atlas',
  status: 'confirmed',
  earliestRequestedDate: '2026-09-01',
  serviceCount: 2,
  addressCount: 1,
  totalWeight: 120.5,
  totalVolume: 2.5,
  totalPackages: 3,
  services: [
    {
      id: SERVICE_ID,
      serviceNumber: 'SRV-1',
      serviceCode: 'LOAD',
      isLoading: true,
      serviceName: 'Chargement',
      status: 'ready_to_plan',
      addressId: '01JQZ0000000000000ADDR01',
      addressLabel: 'Entrepôt · 20000 Casablanca',
      latitude: 33.59,
      longitude: -7.62,
      requestedDate: '2026-09-01',
      requestedFrom: null,
      requestedTo: null,
      weight: 120.5,
      volume: 2.5,
      packageCount: 3,
    },
  ],
  ...overrides,
})

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
  totalWeight: 0,
  totalVolume: 0,
  totalPackages: 0,
  totalCustomers: 0,
  drivingTimeMinutes: 0,
  workingTimeMinutes: 0,
  distanceMeters: 0,
  status: 'draft',
  stopCount: 0,
  ...overrides,
})

function render(
  result: { planned: string[]; rejected: unknown[] } = { planned: [SERVICE_ID], rejected: [] },
  tours = [tour()],
) {
  const sent: unknown[] = []

  server.use(
    http.get(`${API}/statuses`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/tours/:id/route-geometry`, () =>
      HttpResponse.json({ data: { points: [] } }),
    ),
    http.get(`${API}/planning/pool`, () => HttpResponse.json(paginated([poolOrder()]))),
    http.get(`${API}/tours`, () => HttpResponse.json(paginated(tours))),
    http.post(`${API}/tours/${TOUR_ID}/plan`, async ({ request }) => {
      sent.push(await request.json())

      return HttpResponse.json({ data: { tour: tour(), ...result }, meta: [] })
    }),
  )

  renderWithProviders(<PlanningPage />, { membership: withPermissions(['tours.view', 'tours.update']) })

  return sent
}

/**
 * L'écran de planification : le pool à gauche, les brouillons à droite.
 *
 * Le serveur décide et l'écran rapporte — le regroupement, l'ordre des
 * chargements et les refus viennent de lui.
 */
describe('écran de planification', () => {
  it('montre ce qui attend et les tournées en préparation', async () => {
    render()

    expect(await screen.findByText('CMD-42')).toBeInTheDocument()
    expect(screen.getByText('Meubles Atlas')).toBeInTheDocument()
    expect(await screen.findByText('TR-001')).toBeInTheDocument()
  })

  /**
   * Sans tournée choisie, rien à verser : proposer le bouton mènerait à un
   * appel sans destination.
   */
  it('n’offre de planifier qu’une fois la tournée choisie', async () => {
    render()

    await screen.findByText('CMD-42')
    expect(screen.queryByRole('button', { name: /Planifier la commande/ })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /TR-001/ }))

    expect(
      await screen.findByRole('button', { name: /Planifier la commande/ }),
    ).toBeInTheDocument()
  })

  /** Planifier une commande prend tous ses services : pas de sélecteur. */
  it('envoie la commande entière', async () => {
    const sent = render()

    await screen.findByText('CMD-42')
    await userEvent.click(screen.getByRole('button', { name: /TR-001/ }))
    await userEvent.click(await screen.findByRole('button', { name: /Planifier la commande/ }))

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toEqual({ orderIds: [ORDER_ID] })
  })

  it('envoie un seul service quand on déplie', async () => {
    const sent = render()

    await screen.findByText('CMD-42')
    await userEvent.click(screen.getByRole('button', { name: /TR-001/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Déplier' }))

    const service = await screen.findByText('Chargement')
    await userEvent.click(
      within(service.closest('li') as HTMLElement).getByRole('button', { name: 'Planifier' }),
    )

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toEqual({ orderServiceIds: [SERVICE_ID] })
  })

  /**
   * Les refus du serveur sont rapportés tels quels : l'écran ne les devine pas
   * et ne les tait pas.
   */
  it('rapporte le motif d’un service refusé', async () => {
    render({
      planned: [],
      rejected: [{ orderServiceId: SERVICE_ID, reason: 'already_assigned' }],
    })

    await screen.findByText('CMD-42')
    await userEvent.click(screen.getByRole('button', { name: /TR-001/ }))
    await userEvent.click(await screen.findByRole('button', { name: /Planifier la commande/ }))

    expect(await screen.findByText(/déjà planifié dans une autre tournée/)).toBeInTheDocument()
  })
})

/** Glisse une charge de planification sur une cible, comme le navigateur le ferait. */
function dragOnto(target: HTMLElement, payload: Record<string, unknown>) {
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

  fireEvent.dragOver(target, { dataTransfer })
  fireEvent.drop(target, { dataTransfer })
}

describe('glisser dans la vue en panneaux', () => {
  /**
   * Lâcher une commande sur un brouillon désigne déjà la tournée : exiger de
   * l'avoir choisie avant ferait faire deux gestes pour une seule intention.
   */
  it('planifie sans avoir choisi la tournée au préalable', async () => {
    const sent = render()

    await screen.findByText('CMD-42')

    dragOnto(screen.getByTestId(`draft-panel-${TOUR_ID}`), {
      kind: 'order',
      id: ORDER_ID,
      label: 'CMD-42',
    })

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toEqual({ orderIds: [ORDER_ID] })
  })
})

/**
 * La carte répond à une question que les panneaux ne posent pas : qui occupe
 * déjà le terrain ?
 */
describe('vue carte', () => {
  const planned = tour({
    id: '01JQZ0000000000000TOUR02',
    tourNumber: 'TR-DEJA',
    status: 'confirmed',
    stopCount: 1,
    stops: [
      {
        id: '01JQZ0000000000000STOP01',
        tourId: '01JQZ0000000000000TOUR02',
        addressId: '01JQZ0000000000000ADDR09',
        sequence: 1,
        status: 'pending',
        addressLabel: 'Client · 20100 Casablanca',
        latitude: 33.55,
        longitude: -7.6,
        serviceCount: 2,
        orderServiceIds: ['01JQZ000000000000OSRV09'],
      },
    ],
  })

  it('montre les tournées qui portent déjà des commandes, brouillon ou non', async () => {
    render(undefined, [tour(), planned])

    await screen.findByText('CMD-42')
    await userEvent.click(screen.getByRole('button', { name: 'Vue carte' }))

    await waitFor(() => expect(document.querySelector('.leaflet-container')).not.toBeNull())

    // Le panneau de gauche ne detaille que la tournee choisie ; les autres
    // restent accessibles par leur numero. TR-DEJA apparait donc en bouton et
    // dans la legende de la carte.
    expect(await screen.findAllByText('TR-DEJA')).toHaveLength(2)

    // Le planificateur est nomme en haut : une brouillon n'appartient qu'a qui
    // l'a ouverte.
    expect(screen.getByText('Connecté comme')).toBeInTheDocument()
  })

  /** Les arrêts ne sont demandés que par la vue qui les trace. */
  it('ne demande les arrêts qu’en mode carte', async () => {
    const calls: URL[] = []

    server.use(
      http.get(`${API}/statuses`, () => HttpResponse.json(paginated([]))),
      http.get(`${API}/planning/pool`, () => HttpResponse.json(paginated([poolOrder()]))),
      http.get(`${API}/tours`, ({ request }) => {
        calls.push(new URL(request.url))

        return HttpResponse.json(paginated([tour()]))
      }),
    )

    renderWithProviders(<PlanningPage />, {
      membership: withPermissions(['tours.view', 'tours.update']),
    })

    await screen.findByText('CMD-42')
    await waitFor(() => expect(calls[0].searchParams.get('withStops')).toBe('0'))

    await userEvent.click(screen.getByRole('button', { name: 'Vue carte' }))

    await waitFor(() => {
      const last = calls.at(-1)
      expect(last?.searchParams.get('withStops')).toBe('1')
      // Le filtre brouillon tombe : les tournees confirmees occupent aussi
      // le terrain.
      expect(last?.searchParams.get('status')).toBeNull()
    })
  })
})

/**
 * Les trois panneaux de la carte partagent un seul état : ce qui roule à
 * gauche, le terrain au centre, ce qui attend à droite.
 */
describe('panneaux de la vue carte', () => {
  it('cherche dans les commandes à planifier', async () => {
    render()

    await screen.findByText('CMD-42')
    await userEvent.click(screen.getByRole('button', { name: 'Vue carte' }))

    expect(
      await screen.findByLabelText('Rechercher une commande à planifier'),
    ).toBeInTheDocument()
  })

  /**
   * Regarder où est une commande et décider qu'elle part sont deux intentions :
   * les confondre ferait planifier par mégarde.
   */
  it('n’offre de planifier qu’une fois la tournée choisie', async () => {
    const sent = render()

    await screen.findByText('CMD-42')
    await userEvent.click(screen.getByRole('button', { name: 'Vue carte' }))

    await screen.findByText('Connecté comme')
    expect(screen.queryByRole('button', { name: 'Planifier la commande' })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /TR-001/ }))
    await userEvent.click(await screen.findByRole('button', { name: 'Planifier la commande' }))

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toEqual({ orderIds: [ORDER_ID] })
  })

  /**
   * Sans fournisseur de géométrie, les traits sont des vols d'oiseau. La carte
   * le dit plutôt que de les laisser passer pour des routes.
   */
  it('annonce que les traits ne sont pas des routes', async () => {
    render()

    await screen.findByText('CMD-42')
    await userEvent.click(screen.getByRole('button', { name: 'Vue carte' }))

    expect(await screen.findByText(/vol d’oiseau/)).toBeInTheDocument()
  })

  /** Quand un fournisseur rend un tracé, la légende change de discours. */
  it('annonce le tracé réel quand il en a un', async () => {
    render()

    // Apres `render` : le dernier gestionnaire declare l'emporte, et celui de
    // `render` rend un trace vide.
    server.use(
      http.get(`${API}/tours/:id/route-geometry`, () =>
        HttpResponse.json({
          data: { points: [[46.2333, 6.0833], [46.2044, 6.1432]] },
        }),
      ),
    )

    await screen.findByText('CMD-42')
    await userEvent.click(screen.getByRole('button', { name: 'Vue carte' }))
    await userEvent.click(await screen.findByRole('button', { name: /TR-001/ }))

    expect(await screen.findByText(/Tracé routier réel/)).toBeInTheDocument()
  })
})

/**
 * L'exclusivité d'un brouillon, telle que les §22 à §26 la définissent : elle
 * appartient à son créateur et cesse à la validation ou à la libération.
 */
describe('réservation d’un brouillon dans la carte', () => {
  const mine = tour({
    plannedBy: { id: '01JQZ0000000000000000USR1', name: 'Badr Ouali' },
  })
  const started = tour({
    plannedBy: { id: '01JQZ0000000000000000USR1', name: 'Badr Ouali' },
    stopCount: 1,
    stops: [
      {
        id: '01JQZ0000000000000STOP07',
        tourId: TOUR_ID,
        addressId: '01JQZ0000000000000ADDR07',
        sequence: 1,
        status: 'pending',
        addressLabel: 'Client · 1200 Genève',
        latitude: 46.2044,
        longitude: 6.1432,
        serviceCount: 2,
        orderServiceIds: ['01JQZ000000000000OSRV07', '01JQZ000000000000OSRV08'],
        orders: [],
      },
    ],
  })

  const hers = tour({
    id: '01JQZ0000000000000TOUR09',
    tourNumber: 'TR-AUTRE',
    plannedBy: { id: '01JQZ00000000000000AUTR1', name: 'Sara Amrani' },
  })

  const openMap = async () => {
    await screen.findByText('CMD-42')
    await userEvent.click(screen.getByRole('button', { name: 'Vue carte' }))
    await screen.findByText('Connecté comme')
  }

  /** §25 : nommer qui tient le brouillon, pour pouvoir le lui demander. */
  it('nomme le planificateur qui tient le brouillon', async () => {
    render(undefined, [hers])
    await openMap()

    await userEvent.click(screen.getByRole('button', { name: /TR-AUTRE/ }))

    expect(
      await screen.findByText(/Planification en cours par Sara Amrani/),
    ).toBeInTheDocument()
  })

  /**
   * **Régression.** Les boutons se déduisaient d'une variable de composant :
   * fermer la fenêtre l'effaçait, et rouvrir la tournée ne proposait plus de
   * conclure alors qu'elle était toujours réservée. Ils se déduisent maintenant
   * du contenu, qui survit à tout.
   */
  it('propose de conclure dès l’ouverture d’un plan commencé', async () => {
    render(undefined, [started])
    await openMap()

    await userEvent.click(screen.getByRole('button', { name: /TR-001/ }))

    expect(
      await screen.findByRole('button', { name: /Valider la planification/ }),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Annuler la planification/ })).toBeInTheDocument()
  })

  /** Un brouillon vide n'attend rien : ni à valider, ni à annuler. */
  it('ne propose rien sur un brouillon vide', async () => {
    render(undefined, [mine])
    await openMap()

    await userEvent.click(screen.getByRole('button', { name: /TR-001/ }))

    expect(
      screen.queryByRole('button', { name: /Valider la planification/ }),
    ).not.toBeInTheDocument()
  })

  /**
   * Une tournée à la fois : deux plans ouverts en parallèle sur un même fond de
   * carte se confondent.
   */
  it('ferme les autres tournées tant qu’on n’a pas conclu', async () => {
    render(undefined, [started, hers])
    await openMap()

    await userEvent.click(screen.getByRole('button', { name: /TR-001/ }))
    await screen.findByRole('button', { name: /Valider la planification/ })

    expect(screen.getByRole('button', { name: 'TR-AUTRE' })).toBeDisabled()
  })

  /** Annuler rend les commandes au pool : le geste s'annonce avant d'agir. */
  it('annonce ce que l’annulation rend au pool', async () => {
    render(undefined, [started])
    await openMap()

    await userEvent.click(screen.getByRole('button', { name: /TR-001/ }))
    await userEvent.click(
      await screen.findByRole('button', { name: /Annuler la planification/ }),
    )

    expect(await screen.findByText('Annuler cette planification ?')).toBeInTheDocument()
    expect(screen.getByText(/reviennent dans les commandes à planifier/)).toBeInTheDocument()
  })
})
