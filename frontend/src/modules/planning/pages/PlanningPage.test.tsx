import { screen, waitFor, within } from '@testing-library/react'
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

function render(result: { planned: string[]; rejected: unknown[] } = { planned: [SERVICE_ID], rejected: [] }) {
  const sent: unknown[] = []

  server.use(
    http.get(`${API}/statuses`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/planning/pool`, () => HttpResponse.json(paginated([poolOrder()]))),
    http.get(`${API}/tours`, () => HttpResponse.json(paginated([tour()]))),
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
