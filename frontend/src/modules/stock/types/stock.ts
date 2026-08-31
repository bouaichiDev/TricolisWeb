/**
 * Stock client chez le transporteur.
 *
 * Cinq entités, cinq rôles distincts — le diagramme les sépare pour une
 * raison :
 *
 * - `StockItem` est la **référence physique** d'un client. Elle pointe vers
 *   l'article de catalogue par `catalogItemId`, qui est facultatif : une
 *   marchandise peut arriver en dépôt sans figurer au catalogue.
 * - `StockLocation` est l'**endroit**, propriété d'un dépôt et non d'un client.
 * - `StockBalance` est la **quantité par emplacement**. Il n'y a pas de
 *   quantité « de l'article » : la même référence peut dormir dans trois
 *   emplacements de deux dépôts, avec des réservations différentes.
 * - `StockMovement` est l'**historique**. Aucune quantité ne se saisit
 *   directement : elle se déplace, et le solde en découle.
 * - `StockReservation` est la **promesse** faite à une ligne de commande.
 *
 * Les quantités arrivent en **chaînes** : l'API rend des `decimal(12,3)`, et un
 * `100.500` relu en flottant se réaffiche `100.49999999999999`. Elles ne sont
 * converties qu'au moment de l'affichage, jamais avant d'être renvoyées.
 */

/** Formes `*CompactResource` : ce que les relations imbriquées exposent. */
export interface StockItemCompact {
  id: string
  articleCode: string
  barcode: string | null
  status: string
}

export interface StockLocationCompact {
  id: string
  locationCode: string
  zoneCode: string | null
  status: string
}

export interface CustomerCompact {
  id: string
  code: string
  name: string
  status: string | null
}

export interface UserCompact {
  id: string
  firstName: string | null
  lastName: string | null
  email: string | null
}

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

export interface StockItemDetail extends StockItem {
  customer?: CustomerCompact
  balances?: StockBalance[]
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

export interface StockLocationDetail extends StockLocation {
  parent?: StockLocationCompact | null
  children?: StockLocationCompact[]
  balances?: StockBalance[]
}

/**
 * Nœud de `GET /stock-locations/tree`.
 *
 * La ressource d'arbre est volontairement plus maigre que celle de liste :
 * `aisle`, `rack` et `level` n'y figurent pas. Un arbre se parcourt par codes,
 * et le serveur charge **tout** le dépôt d'un coup — chaque champ en plus s'y
 * multiplie par le nombre d'emplacements.
 */
export interface StockLocationTreeNode {
  id: string
  depotId: string
  parentLocationId: string | null
  zoneCode: string | null
  locationCode: string
  status: string
  children: StockLocationTreeNode[]
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

export interface StockBalanceDetail extends StockBalance {
  stockItem?: StockItemCompact
  stockLocation?: StockLocationCompact
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

export interface StockMovementDetail extends StockMovement {
  stockItem?: StockItemCompact
  sourceLocation?: StockLocationCompact | null
  destinationLocation?: StockLocationCompact | null
  creator?: UserCompact | null
}

export interface StockReservation {
  id: string
  stockItemId: string
  stockLocationId: string
  orderLineId: string
  quantity: number | string
  status: string
  reservedAt: string | null
  releasedAt: string | null
}

export interface StockReservationDetail extends StockReservation {
  stockItem?: StockItemCompact
  stockLocation?: StockLocationCompact
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

/** Une réservation libérée ne peut plus l'être : le serveur répond 409. */
export function isReleased(reservation: Pick<StockReservation, 'releasedAt'>): boolean {
  return reservation.releasedAt !== null
}
