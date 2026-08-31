import { HttpResponse, http } from 'msw'

import { paginated } from '@/test/fixtures'
import { API, server } from '@/test/server'

/**
 * Jeux de données du module Stock, à la forme réelle des ressources.
 *
 * Ils reproduisent ce que le serveur renvoie, y compris ses **absences** : une
 * quantité arrive en chaîne décimale, `StockItemListResource` ne porte aucun
 * solde, et `StockReservationListResource` ne résout pas l'emplacement. Un jeu
 * de test plus généreux que l'API ferait passer des tests que la production
 * mettrait en échec.
 */
export const AGENCY_ID = '01JQZ0000000000000000AGY1'
export const DEPOT_ID = '01JQZ00000000000000DEPO1'
export const LOCATION_ID = '01JQZ00000000000000LOCA1'
export const CHILD_LOCATION_ID = '01JQZ00000000000000LOCA2'
export const ITEM_ID = '01JQZ00000000000000ITEM1'
export const CUSTOMER_ID = '01JQZ00000000000000CUST1'
export const RESERVATION_ID = '01JQZ000000000000000RES1'
export const MOVEMENT_ID = '01JQZ000000000000000MOV1'
export const ORDER_LINE_ID = '01JQZ0000000000000LINE01'

export const stockLocation = (overrides: Record<string, unknown> = {}) => ({
  id: LOCATION_ID,
  depotId: DEPOT_ID,
  parentLocationId: null,
  zoneCode: 'A',
  aisle: '01',
  rack: '2',
  level: '3',
  locationCode: 'A-01-2-3',
  barcode: null,
  status: 'active',
  childCount: 0,
  ...overrides,
})

export const stockItem = (overrides: Record<string, unknown> = {}) => ({
  id: ITEM_ID,
  customerId: CUSTOMER_ID,
  catalogItemId: null,
  articleCode: 'PAL-EUR',
  barcode: '3401234567890',
  description: 'Palette Europe',
  status: 'active',
  customerName: 'Client Nord',
  ...overrides,
})

/** Les décimaux arrivent en chaînes : `decimal(12,3)` rendu par Laravel. */
export const stockBalance = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ00000000000000BAL01',
  stockItemId: ITEM_ID,
  stockLocationId: LOCATION_ID,
  quantity: '100.000',
  reservedQuantity: '20.000',
  availableQuantity: '80.000',
  updatedAt: '2026-08-20T09:00:00+00:00',
  articleCode: 'PAL-EUR',
  locationCode: 'A-01-2-3',
  ...overrides,
})

export const stockMovement = (overrides: Record<string, unknown> = {}) => ({
  id: MOVEMENT_ID,
  stockItemId: ITEM_ID,
  sourceLocationId: null,
  destinationLocationId: LOCATION_ID,
  movementType: 'reception',
  quantity: '100.000',
  sourceEntityType: null,
  sourceEntityId: null,
  createdBy: null,
  createdAt: '2026-08-20T09:00:00+00:00',
  ...overrides,
})

export const stockReservation = (overrides: Record<string, unknown> = {}) => ({
  id: RESERVATION_ID,
  stockItemId: ITEM_ID,
  stockLocationId: LOCATION_ID,
  orderLineId: ORDER_LINE_ID,
  quantity: '20.000',
  status: 'active',
  reservedAt: '2026-08-20T10:00:00+00:00',
  releasedAt: null,
  ...overrides,
})

const statusRow = (source: string, code: string, label: string, rank: number) => ({
  id: `01JQZ000000000000STAT0${rank}`,
  source,
  status: rank,
  code,
  label,
  icon: null,
  active: true,
  isToSend: false,
  allowsContentChanges: false,
  requiresReason: false,
  position: rank * 10,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
})

/**
 * Référentiel des statuts de stock, filtré par `source` comme le serveur le
 * fait. Les codes sont ceux que `StatusSeeder` sème réellement.
 */
export function serveStatuses() {
  const bySource: Record<string, [string, string][]> = {
    stock_item: [
      ['active', 'Actif'],
      ['archived', 'Archivé'],
    ],
    stock_location: [
      ['active', 'Actif'],
      ['inactive', 'Inactif'],
      ['blocked', 'Bloqué'],
    ],
    stock_reservation: [
      ['active', 'Active'],
      ['confirmed', 'Confirmée'],
      ['released', 'Libérée'],
    ],
  }

  server.use(
    http.get(`${API}/statuses`, ({ request }) => {
      const source = new URL(request.url).searchParams.get('source') ?? ''
      const codes = bySource[source] ?? []

      return HttpResponse.json(
        paginated(codes.map(([code, label], index) => statusRow(source, code, label, index + 1))),
      )
    }),
  )
}

/** Agences et dépôts : la cascade que tout choix d'emplacement traverse. */
export function serveScope() {
  server.use(
    http.get(`${API}/agencies`, () =>
      HttpResponse.json(
        paginated([
          {
            id: AGENCY_ID,
            organizationId: '01JQZ0000000000000000ORG1',
            code: 'AG01',
            name: 'Agence Nord',
            status: 'active',
            createdAt: '2026-02-01T10:00:00.000000Z',
            updatedAt: '2026-02-01T10:00:00.000000Z',
          },
        ]),
      ),
    ),
    http.get(`${API}/agencies/${AGENCY_ID}/depots`, () =>
      HttpResponse.json(
        paginated([
          {
            id: DEPOT_ID,
            agencyId: AGENCY_ID,
            code: 'DEP01',
            name: 'Dépôt Casablanca',
            status: 'active',
            createdAt: '2026-02-01T10:00:00.000000Z',
            updatedAt: '2026-02-01T10:00:00.000000Z',
          },
        ]),
      ),
    ),
  )
}

export function serveCustomers() {
  server.use(
    http.get(`${API}/customers`, () =>
      HttpResponse.json(
        paginated([
          {
            id: CUSTOMER_ID,
            organizationId: '01JQZ0000000000000000ORG1',
            code: 'CLI01',
            name: 'Client Nord',
            status: 'active',
          },
        ]),
      ),
    ),
  )
}
