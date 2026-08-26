import { screen, waitFor } from '@testing-library/react'
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
    },
    {
      id: '01JQZ0000000000000STOP02',
      tourId: TOUR_ID,
      addressId: '01JQZ0000000000000ADDR02',
      sequence: 2,
      status: 'pending',
      addressLabel: 'Client · 20100 Casablanca',
      serviceCount: 1,
    },
  ],
  ...overrides,
})

function render() {
  const calls: URL[] = []

  server.use(
    http.get(`${API}/statuses`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/tours`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated([tour()]))
    }),
  )

  renderWithProviders(<TourListPage />, { membership: withPermissions(['tours.view']) })

  return calls
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
    const calls = render()

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
    const calls = render()

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
