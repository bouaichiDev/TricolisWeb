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

    // La legende nomme chaque tournee tracee : c'est elle qui dit laquelle
    // porte quelle couleur.
    expect(await screen.findByText('TR-DEJA')).toBeInTheDocument()
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
