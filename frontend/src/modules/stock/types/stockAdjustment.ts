/**
 * Demande de correction de stock, telle que le transporteur la lit.
 *
 * Calquée sur `StockAdjustmentRequestListResource` et sa fiche. Les quantités
 * arrivent en chaînes décimales (`"47.500"`) ; `difference`, `drift` et
 * `corrections` sont **calculés par le serveur** en bcmath — l'écran les
 * affiche, il ne les recalcule pas. `status` est un code du référentiel
 * (source `stock_adjustment_request`).
 */

/** Emplacement complet, comme le client le lit : dépôt, zone, allée, rack, niveau, code. */
export interface AdjustmentLocation {
  id: string
  depot: string | null
  zoneCode: string | null
  aisle: string | null
  rack: string | null
  level: string | null
  locationCode: string | null
}

export interface StockAdjustmentRequest {
  id: string
  customerId: string
  customer: { id: string; code: string; name: string } | null
  stockItemId: string
  stockItem: { id: string; articleCode: string; description: string | null } | null
  stockLocationId: string | null
  location: AdjustmentLocation | null
  displayedQuantity: string
  requestedQuantity: string
  difference: string
  reason: string
  comment: string | null
  status: string
  requester: { id: string; name: string } | null
  reviewer: { id: string; name: string } | null
  reviewedAt: string | null
  reviewComment: string | null
  stockMovementId: string | null
  createdAt: string
  updatedAt: string
}

/** Le mouvement qu'une acceptation créerait à un emplacement donné. */
export interface AdjustmentCorrection {
  location: AdjustmentLocation
  currentQuantity: string
  reservedQuantity: string
  direction: 'inbound' | 'outbound' | 'none'
  quantity: string
}

export interface StockAdjustmentRequestDetail extends StockAdjustmentRequest {
  currentQuantity: string
  drift: string
  movementType: string
  stockMovement: {
    id: string
    movementType: string
    quantity: string
    sourceLocationId: string | null
    destinationLocationId: string | null
    createdAt: string
  } | null
  corrections: AdjustmentCorrection[]
  actions: { canApprove: boolean; canReject: boolean }
}

/** `ListStockAdjustmentRequestRequest` ; `StockAdjustmentRequestListQuery::SORTABLE`. */
export interface StockAdjustmentRequestFilters {
  page: number
  perPage: number
  search?: string
  status?: string[]
  customerId?: string
  stockItemId?: string
  stockLocationId?: string
  createdFrom?: string
  createdTo?: string
  sort?: 'created_at' | 'reviewed_at' | 'status' | 'requested_quantity'
  direction?: 'asc' | 'desc'
}

/** Le libellé lisible d'un emplacement, du dépôt au code. */
export function formatAdjustmentLocation(location: AdjustmentLocation | null): string | null {
  if (location === null) return null

  const path = [location.zoneCode, location.aisle, location.rack, location.level].filter(Boolean)

  return [location.depot, location.locationCode, path.length > 0 ? `(${path.join('-')})` : null]
    .filter(Boolean)
    .join(' · ')
}

/** Une quantité signée, lue sur la chaîne du serveur : `-2.500` est négative. */
export function isNegativeQuantity(value: string): boolean {
  return value.trim().startsWith('-')
}

export function isZeroQuantity(value: string): boolean {
  return Number.parseFloat(value) === 0
}
