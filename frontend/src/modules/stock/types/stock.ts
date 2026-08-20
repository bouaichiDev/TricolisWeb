/**
 * Stock client chez le transporteur.
 *
 * Trois entités, trois rôles distincts — le diagramme les sépare pour une
 * raison :
 *
 * - `StockItem` est la **référence physique** d'un client. Elle pointe vers
 *   l'article de catalogue par `catalogItemId`, qui est facultatif : une
 *   marchandise peut arriver en dépôt sans figurer au catalogue.
 * - `StockBalance` est la **quantité par emplacement**. Il n'y a pas de
 *   quantité « de l'article » : la même référence peut dormir dans trois
 *   emplacements de deux dépôts, avec des réservations différentes.
 * - `StockMovement` est l'**historique**. Aucune quantité ne se saisit
 *   directement : elle se déplace, et le solde en découle.
 */
export interface StockItem {
  id: string
  customerId: string
  catalogItemId: string | null
  articleCode: string
  barcode: string | null
  description: string | null
  status: string
  customerName?: string
}

export interface StockLocation {
  id: string
  depotId: string
  parentLocationId: string | null
  zoneCode: string | null
  aisle: string | null
  rack: string | null
  level: string | null
  locationCode: string
  barcode: string | null
  status: string
  childCount?: number
}

export interface StockBalance {
  id: string
  stockItemId: string
  stockLocationId: string
  quantity: number | string
  reservedQuantity: number | string
  availableQuantity: number | string
  updatedAt: string | null
  articleCode?: string
  locationCode?: string
}

export interface StockMovement {
  id: string
  stockItemId: string
  sourceLocationId: string | null
  destinationLocationId: string | null
  movementType: string
  quantity: number | string
  sourceEntityType: string | null
  sourceEntityId: string | null
  createdBy: string | null
  createdAt: string | null
}

/**
 * Sens d'un mouvement, déduit des emplacements — jamais stocké.
 *
 * `StoreStockMovementRequest` laisse `movementType` libre : « le diagramme n'en
 * énumère aucune valeur ». Ce qui est structurel, c'est la présence d'une
 * source, d'une destination, ou des deux, et `CreateStockMovementAction` ne
 * contrôle que cela.
 */
export type MovementDirection = 'entry' | 'exit' | 'transfer'

export function movementDirection(movement: {
  sourceLocationId: string | null
  destinationLocationId: string | null
}): MovementDirection {
  if (movement.sourceLocationId === null) return 'entry'
  if (movement.destinationLocationId === null) return 'exit'

  return 'transfer'
}

export interface StockBalanceFilters {
  page: number
  perPage: number
  stockItemId?: string
  stockLocationId?: string
  customerId?: string
  availableOnly?: boolean
}

export interface StockMovementFilters {
  page: number
  perPage: number
  stockItemId?: string
  movementType?: string
}

export interface StockLocationFilters {
  page: number
  perPage: number
  search?: string
  depotId?: string
  status?: string
}

export interface StockItemFilters {
  page: number
  perPage: number
  search?: string
  customerId?: string
  catalogItemId?: string
}
