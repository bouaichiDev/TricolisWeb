/** Une tournée telle que `GET /tours/{id}` la rend, avec un arrêt et un service. */
export function makeTour(overrides: Record<string, unknown> = {}) {
  return {
    id: '01TOUR1',
    tourNumber: 'T-ALPHA-1',
    tourDate: '2026-11-03',
    status: 'confirmed',
    statusLabel: 'Confirmée',
    agency: 'Agence Genève',
    depot: null,
    driver: null,
    vehicle: null,
    plannedStartAt: '2026-11-03T07:00:00+01:00',
    plannedEndAt: '2026-11-03T12:00:00+01:00',
    actualStartAt: null,
    actualEndAt: null,
    instructions: 'Quai 3',
    totals: { weight: '800.000', volume: '8.0000', packages: 12, distanceMeters: 42000, drivingMinutes: 90, workingMinutes: 240 },
    stops: [
      {
        id: '01STOP1', sequence: 1, status: 'pending', statusLabel: 'En attente',
        plannedArrivalAt: '2026-11-03T08:00:00+01:00', plannedDepartureAt: null, actualArrivalAt: null, actualDepartureAt: null,
        address: { name: 'Entrepôt Nord', line1: 'Rue du Port 4', line2: null, postalCode: '1201', city: 'Genève', country: 'CH', latitude: null, longitude: null, instructions: 'Sonner', timeWindowFrom: null, timeWindowTo: null },
        services: [
          { id: '01SVC1', status: 'planned', serviceNumber: 'SRV-00042', serviceName: 'Livraison', requestedFrom: null, requestedTo: null, weight: '12.500', volume: '0.2000', packageCount: 3, instructions: null, contacts: [{ firstName: 'Lea', lastName: 'Site', phone: '+41220000000', mobile: null }] },
        ],
      },
    ],
    actions: { canAssign: true },
    ...overrides,
  }
}

export const tourPage = (rows: unknown[]) => ({ data: rows, meta: { currentPage: 1, perPage: 25, total: rows.length, lastPage: 1 } })
