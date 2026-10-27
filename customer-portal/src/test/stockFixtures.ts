import type { StockBalance, StockItem, StockMovement, StockReservation } from '@/modules/stock/types'

const active = { code: 'active', label: 'Actif', icon: null }
const location = { id: '01LOCA', depot: 'Dépôt central', zoneCode: 'A', aisle: '01', rack: '01', level: '1', locationCode: 'A-01-01' }
const item = { id: '01ITEM', articleCode: 'ART-001', description: 'Bibliothèque Billy' }

export const paginated = <T>(rows: T[], meta: Record<string, unknown> = {}) => ({
  data: rows,
  meta: { currentPage: 1, perPage: 20, total: rows.length, lastPage: 1, ...meta },
})

export function makeStockItem(overrides: Partial<StockItem> = {}): StockItem {
  return {
    ...item,
    catalogItemId: '01CAT',
    catalogItem: { id: '01CAT', articleCode: 'BILLY-80', name: 'Bibliothèque Billy 80 cm' },
    barcode: '7612345678901',
    status: active,
    stock: { quantity: '120.500', reservedQuantity: '20.000', availableQuantity: '100.500' },
    actions: { canView: true, canEditMetadata: true, canViewMovements: true, canViewReservations: true, canRequestChange: false },
    ...overrides,
  }
}

export function makeBalance(overrides: Partial<StockBalance> = {}): StockBalance {
  return { id: '01BAL', location, quantity: '50.000', reservedQuantity: '10.000', availableQuantity: '40.000', updatedAt: null, ...overrides }
}

export function makeMovement(overrides: Partial<StockMovement> = {}): StockMovement {
  return {
    id: '01MOV', stockItem: item, movementType: 'inbound', direction: 'entry', quantity: '50.000',
    sourceLocation: null, destinationLocation: location, sourceEntityType: null, sourceEntityId: null, createdAt: '2026-09-28T10:00:00+02:00',
    ...overrides,
  }
}

export function makeReservation(overrides: Partial<StockReservation> = {}): StockReservation {
  return {
    id: '01RES', stockItem: item, location, quantity: '10.000', status: active, isReleased: false,
    reservedAt: '2026-09-29T10:00:00+02:00', releasedAt: null,
    order: { id: '01ORDER', reference: 'IKEA-2026-000001', customerReference: 'IKEA-4587' },
    orderLine: { id: '01LINE', articleCode: 'BILLY-80', name: 'Bibliothèque Billy 80 cm', quantity: '10.000' },
    ...overrides,
  }
}

export const stockFilterOptions = { statuses: [{ code: 'active', label: 'Actif' }, { code: 'archived', label: 'Archivé' }], locations: [location] }
