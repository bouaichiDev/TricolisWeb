import type { Address } from '@/modules/addresses/types/address'
import type { Service } from '@/modules/services/types/service'

/** Ligne de commande — `OrderLineResource`. */
export interface OrderLine {
  id: string
  orderId: string
  catalogItemId: string | null
  parentLineId: string | null
  externalReference: string | null
  articleCode: string | null
  barcode: string | null
  name: string
  description: string | null
  quantity: number | string
  reservedQuantity: number | string | null
  preparedQuantity: number | string | null
  deliveredQuantity: number | string | null
  weight: number | string | null
  volume: number | string | null
  length: number | string | null
  width: number | string | null
  height: number | string | null
  purchasePrice: number | string | null
  sellingPrice: number | string | null
  status: string | null
  /** Calculé par la ressource : la ligne vient-elle d'un article catalogue ? */
  fromCatalog: boolean
}

/**
 * Colis — `PackageResource`.
 *
 * `currentStockLocationId` figure au diagramme et en base ; l'emplacement lui
 * même relève du module Stock, phase ultérieure, donc seul l'identifiant est
 * connu ici.
 */
export interface OrderPackage {
  id: string
  orderId: string
  parentPackageId: string | null
  packageTypeId: string | null
  groupingTypeId: string | null
  currentStockLocationId: string | null
  barcode: string | null
  reference: string | null
  description: string | null
  quantity: number | string | null
  weight: number | string | null
  volume: number | string | null
  length: number | string | null
  width: number | string | null
  height: number | string | null
  status: string | null
  packageType?: { id: string; code: string; name: string } | null
  groupingType?: { id: string; code: string; name: string } | null
  lines?: { id: string; orderLineId: string; quantity: number | string }[]
}

/** Nœud de `GET /orders/{order}/packages/tree`. */
export interface PackageTreeNode {
  id: string
  parentPackageId: string | null
  barcode: string | null
  reference: string | null
  quantity: number | string | null
  weight: number | string | null
  volume: number | string | null
  status: string | null
  children: PackageTreeNode[]
}

/**
 * Contact d'un service — `OrderServiceContactResource`.
 *
 * Les champs sont des **instantanés** : `firstName` et les suivants sont
 * recopiés au moment du rattachement. Modifier le contact partagé plus tard ne
 * change pas ce qu'une commande ancienne a enregistré. L'API les expose sans
 * le suffixe `Snapshot`, qui n'est qu'un nom de colonne.
 */
export interface OrderServiceContact {
  id: string
  orderServiceId: string
  contactId: string | null
  contactRole: string | null
  firstName: string | null
  lastName: string | null
  phone: string | null
  mobile: string | null
  email: string | null
  isPrimary: boolean
  createdAt: string
}

/**
 * Service d'une commande — `OrderServiceResource`.
 *
 * **C'est l'unité opérationnelle adressée** : elle porte l'adresse, la
 * séquence et le créneau. Il n'existe pas d'`OrderStop` dans ce modèle.
 *
 * Ni `plannedFrom`, ni `actualStartAt` : la planification appartient à
 * `TourStopService`, phase suivante, et ces champs sont absents de la
 * ressource comme du modèle.
 */
export interface OrderService {
  id: string
  orderId: string
  serviceId: string
  addressId: string | null
  serviceNumber: string
  sequence: number
  operational: {
    requestedDate: string | null
    requestedFrom: string | null
    requestedTo: string | null
    quantity: number | string
    unit: string | null
    requiredTimeMinutes: number | null
    remainingTimeMinutes: number | null
    weight: number | string | null
    volume: number | string | null
    packageCount: number | null
    instructions: string | null
  }
  billing: {
    customerUnitPrice: number | string | null
    customerTotalPrice: number | string | null
  }
  providerCost: {
    providerUnitCost: number | string | null
    providerTotalCost: number | string | null
  }
  status: string | null
  service?: Service
  address?: Address
  contacts?: OrderServiceContact[]
  packages?: {
    id: string
    packageId: string
    quantity: number | string | null
    handlingInstructions: string | null
    status: string | null
  }[]
}

/** Commande complète — `OrderDetailResource`, tout en un appel. */
export interface OrderDetail {
  id: string
  organizationId: string
  orderNumber: string
  externalReference: string | null
  customerReference: string | null
  orderType: string | null
  groupCode: string | null
  parentOrderId: string | null
  orderDate: string
  source: string | null
  status: string | null
  statusLabel: string | null
  /** Le contenu de la commande peut-il encore être modifié ? */
  allowsContentChanges: boolean
  /** Transitions que le backend accepte, déjà filtrées sur l'assignable. */
  allowedTransitions: string[]
  internalRemark: string | null
  workerRemark: string | null
  weight: number | string | null
  volume: number | string | null
  packageCount: number | null
  currencyCode: string | null
  customerId: string
  agencyId: string
  depotId: string | null
  customer?: { id: string; code: string; name: string }
  agency?: { id: string; code: string; name: string }
  depot?: { id: string; code: string; name: string }
  lines?: OrderLine[]
  packages?: OrderPackage[]
  services?: OrderService[]
  createdAt: string
  updatedAt: string
}

/**
 * Ce que la confirmation d'une commande sortirait du stock, ligne par ligne.
 *
 * Une ligne de commande ne dit pas où sa marchandise se trouve : quand un
 * article dort dans plusieurs emplacements, il faut demander lequel vider. Cet
 * aperçu est consulté **avant** la confirmation, plutôt que de la refuser après.
 */
export interface OrderStockPlanLine {
  orderLineId: string
  name: string
  articleCode: string | null
  quantity: string
  stockItemId: string | null
  stockLocationId: string | null
  /**
   * `resolved` — rien à demander. `ambiguous` — faire choisir dans `locations`.
   * `insufficient` — aucun emplacement ne couvre la quantité. `untracked` —
   * ligne hors catalogue ou article non entreposé. `consumed` — déjà sortie.
   */
  state: 'resolved' | 'ambiguous' | 'insufficient' | 'untracked' | 'consumed'
  locations: {
    id: string
    locationCode: string | null
    zoneCode: string | null
    availableQuantity: string
  }[]
}
