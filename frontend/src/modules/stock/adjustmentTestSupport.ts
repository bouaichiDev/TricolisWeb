import { HttpResponse, http } from 'msw'

import { paginated } from '@/test/fixtures'
import { API, server } from '@/test/server'

import { CUSTOMER_ID, ITEM_ID, LOCATION_ID, MOVEMENT_ID } from './testSupport'

/**
 * Demandes de correction, à la forme réelle de
 * `StockAdjustmentRequestListResource` et de sa fiche : quantités en chaînes,
 * écart et dérive calculés par le serveur, `status` en code du référentiel.
 */
export const ADJUSTMENT_ID = '01JQZ00000000000000ADJ001'
export const OTHER_LOCATION_ID = '01JQZ00000000000000LOCB2'

const location = (id = LOCATION_ID, code = 'A-01-2-3') => ({
  id,
  depot: 'Dépôt Casablanca',
  zoneCode: 'A',
  aisle: '01',
  rack: '2',
  level: '3',
  locationCode: code,
})

export const adjustmentRow = (overrides: Record<string, unknown> = {}) => ({
  id: ADJUSTMENT_ID,
  customerId: CUSTOMER_ID,
  customer: { id: CUSTOMER_ID, code: 'CLI01', name: 'Client Nord' },
  stockItemId: ITEM_ID,
  stockItem: { id: ITEM_ID, articleCode: 'PAL-EUR', description: 'Palette Europe' },
  stockLocationId: LOCATION_ID,
  location: location(),
  displayedQuantity: '50.000',
  requestedQuantity: '47.500',
  difference: '-2.500',
  reason: 'Inventaire du 30 septembre',
  comment: 'Deux cartons abîmés',
  status: 'pending',
  requester: { id: '01JQZ0000000000000000USR9', name: 'Ingrid Admin' },
  reviewer: null,
  reviewedAt: null,
  reviewComment: null,
  stockMovementId: null,
  createdAt: '2026-09-30T09:00:00+00:00',
  updatedAt: '2026-09-30T09:00:00+00:00',
  ...overrides,
})

export const adjustmentDetail = (overrides: Record<string, unknown> = {}) => ({
  ...adjustmentRow(),
  currentQuantity: '50.000',
  drift: '0.000',
  movementType: 'adjustment',
  stockMovement: null,
  corrections: [
    {
      location: location(),
      currentQuantity: '50.000',
      reservedQuantity: '0.000',
      direction: 'outbound',
      quantity: '2.500',
    },
  ],
  actions: { canApprove: true, canReject: true },
  ...overrides,
})

/** Une demande « tout l'article » : deux emplacements où choisir. */
export const wholeItemDetail = (overrides: Record<string, unknown> = {}) =>
  adjustmentDetail({
    stockLocationId: null,
    location: null,
    displayedQuantity: '120.000',
    requestedQuantity: '118.000',
    difference: '-2.000',
    currentQuantity: '120.000',
    corrections: [
      { location: location(), currentQuantity: '50.000', reservedQuantity: '0.000', direction: 'outbound', quantity: '2.000' },
      { location: location(OTHER_LOCATION_ID, 'B-02'), currentQuantity: '70.000', reservedQuantity: '10.000', direction: 'outbound', quantity: '2.000' },
    ],
    ...overrides,
  })

export const approvedMovement = {
  id: MOVEMENT_ID,
  movementType: 'adjustment',
  quantity: '2.500',
  sourceLocationId: LOCATION_ID,
  destinationLocationId: null,
  createdAt: '2026-09-30T10:00:00+00:00',
}

/** Référentiel `stock_adjustment_request`, tel que `StatusSeeder` le sème. */
export function serveAdjustmentStatuses() {
  const codes: [string, string][] = [
    ['pending', 'En attente'],
    ['approved', 'Acceptée'],
    ['rejected', 'Refusée'],
  ]

  server.use(
    http.get(`${API}/statuses`, ({ request }) => {
      const source = new URL(request.url).searchParams.get('source') ?? ''
      const rows = source !== 'stock_adjustment_request' ? [] : codes.map(([code, label], index) => ({
        id: `01JQZ00000000000000STAT${index}`,
        source,
        status: index + 1,
        code,
        label,
        icon: null,
        active: true,
        isToSend: false,
        allowsContentChanges: false,
        requiresReason: false,
        position: (index + 1) * 10,
        createdAt: '2026-08-01T09:00:00+00:00',
        updatedAt: '2026-08-01T09:00:00+00:00',
      }))

      return HttpResponse.json(paginated(rows))
    }),
  )
}

/** La fiche d'une demande, servie telle quelle. */
export function serveAdjustment(detail: Record<string, unknown> = adjustmentDetail()) {
  serveAdjustmentStatuses()
  server.use(
    http.get(`${API}/stock-adjustment-requests/${ADJUSTMENT_ID}`, () =>
      HttpResponse.json({ data: detail, meta: [] }),
    ),
  )
}
