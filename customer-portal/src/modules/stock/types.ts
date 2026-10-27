import type { StatusView } from '@/modules/orders/types'

/** Quantités en chaînes décimales (3 décimales) : jamais converties en flottant pour calculer. */
export interface StockTotals {
  quantity: string
  reservedQuantity: string
  availableQuantity: string
}

export interface StockSummary extends StockTotals {
  itemsCount: number
}

/** Capacités calculées par le backend : le portail n'en déduit aucune. */
export interface StockItemActions {
  canView: boolean
  canEditMetadata: boolean
  canViewMovements: boolean
  canViewReservations: boolean
  /** Déposer une demande de correction — jamais modifier le stock. */
  canRequestChange: boolean
}

export interface StockItem {
  id: string
  catalogItemId: string | null
  catalogItem: { id: string; articleCode: string; name: string } | null
  articleCode: string
  barcode: string | null
  description: string | null
  status: StatusView
  stock: StockTotals
  actions: StockItemActions
}

export interface StockLocation {
  id: string
  depot: string | null
  zoneCode: string | null
  aisle: string | null
  rack: string | null
  level: string | null
  locationCode: string
}

export interface StockBalance extends StockTotals {
  id: string
  location: StockLocation | null
  updatedAt: string | null
}

export interface StockMovement {
  id: string
  stockItem: { id: string; articleCode: string; description: string | null }
  movementType: string
  direction: 'entry' | 'exit' | 'transfer'
  quantity: string
  sourceLocation: StockLocation | null
  destinationLocation: StockLocation | null
  sourceEntityType: string | null
  sourceEntityId: string | null
  createdAt: string | null
}

export interface StockReservation {
  id: string
  stockItem: { id: string; articleCode: string; description: string | null }
  location: StockLocation | null
  quantity: string
  status: StatusView
  isReleased: boolean
  reservedAt: string | null
  releasedAt: string | null
  order: { id: string; reference: string; customerReference: string | null } | null
  orderLine: { id: string; articleCode: string | null; name: string | null; quantity: string } | null
}

export interface StockFilters {
  search?: string
  status?: string
  locationId?: string
  stockItemId?: string
  orderId?: string
  movementType?: string
  createdFrom?: string
  createdTo?: string
  state?: string
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  perPage?: number
}

export interface StockFilterOptions {
  statuses: { code: string; label: string }[]
  locations: StockLocation[]
}

export interface StockItemPayload {
  catalogItemId?: string | null
  articleCode?: string
  barcode?: string | null
  description?: string | null
  status?: string | null
}
