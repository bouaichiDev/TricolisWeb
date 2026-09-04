import { screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import type { Tour } from '@/modules/tours/types/tour'
import { renderWithProviders } from '@/test/renderWithProviders'

import { PlannedToursPanel } from './PlannedToursPanel'

const TOUR_ID = '01JQZ0000000000000TOUR01'
const STOPS = ['01JQZ00000000000000STOP1', '01JQZ00000000000000STOP2']

const tour = (): Tour =>
  ({
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
    plannedStartAt: null,
    plannedEndAt: null,
    totalWeight: 0,
    totalVolume: 0,
    totalPackages: 0,
    totalCustomers: 0,
    drivingTimeMinutes: 0,
    workingTimeMinutes: 0,
    distanceMeters: 0,
    status: 'draft',
    stopCount: 2,
    stops: STOPS.map((id, index) => ({
      id,
      tourId: TOUR_ID,
      addressId: `ADR-${index + 1}`,
      sequence: index + 1,
      status: 'pending',
      addressLabel: `Arrêt ${index + 1}`,
      serviceCount: 1,
      latitude: 46.2,
      longitude: 6.14,
    })),
    legs: [{ tourStopId: STOPS[1], distanceMeters: 8400, travelMinutes: 12 }],
  }) as Tour

/**
 * Le mode carte répond à la même question que les colonnes : cet ordre
 * tient-il ? Il doit donc porter les mêmes chiffres — un temps de route visible
 * d'un côté et absent de l'autre ferait douter du bon.
 */
describe('le trajet dans le mode carte', () => {
  it('sépare deux arrêts par leur temps de route et leur distance', () => {
    renderWithProviders(
      <PlannedToursPanel
        tours={[tour()]}
        selectedTourId={TOUR_ID}
        onSelectTour={() => undefined}
        onFocusStop={() => undefined}
      />,
    )

    const band = within(screen.getByTestId(`tour-leg-${STOPS[1]}`))

    expect(band.getByText('12 min de route')).toBeInTheDocument()
    expect(band.getByText('8,4 km')).toBeInTheDocument()
    expect(screen.queryByTestId(`tour-leg-${STOPS[0]}`)).not.toBeInTheDocument()
  })
})
