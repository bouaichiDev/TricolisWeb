/**
 * Énumérations relevées sur le backend, centralisées ici.
 *
 * Le §8 du prompt corrigé l'exige : une seule déclaration, jamais dupliquée
 * dans les composants. Les libellés passent par i18next.
 */
export const ORDER_STATUSES = [
  'draft',
  'confirmed',
  'ready',
  'partially_planned',
  'planned',
  'in_progress',
  'completed',
  'cancelled',
  'partially_invoiced',
  'invoiced',
] as const

/**
 * Statuts qu'un opérateur peut poser lui-même — relevé sur
 * `OrderStatus::manuallyAssignable()`.
 *
 * Les statuts de planification et de facturation sont produits par leurs
 * modules : les proposer à la saisie laisserait déclarer une commande
 * « planifiée » sans tournée. Cette liste ne sert qu'à **expliquer** pourquoi
 * un statut n'est pas proposé ; l'autorisation reste celle du serveur.
 */
export const ORDER_MANUALLY_ASSIGNABLE = ['draft', 'confirmed', 'ready', 'cancelled'] as const

export const ORDER_SOURCES = [
  'internal',
  'customer_portal',
  'rest_api',
  'csv_import',
  'excel_import',
  'xml_import',
  // Ajoutee avec l'import de fichiers : le lecteur accepte le JSON, et une
  // commande venue d'un JSON marquee `csv_import` serait fausse.
  'json_import',
  'stock',
  'catalog',
] as const

export const ORDER_SERVICE_STATUSES = [
  'draft',
  'pending',
  'ready_to_plan',
  'planned',
  'in_progress',
  'completed',
  'failed',
  'cancelled',
  'invoiced',
] as const

/** Colonnes acceptées par `OrderListQuery` ; toute autre renvoie 422. */
export const ORDER_SORTABLE = ['order_number', 'order_date', 'status', 'created_at'] as const

/** Ligne de la liste — `OrderListResource`, allégée à dessein. */
export interface OrderListItem {
  id: string
  orderNumber: string
  externalReference: string | null
  customerReference: string | null
  customerId: string
  agencyId: string
  depotId: string | null
  orderType: string | null
  orderDate: string
  source: string | null
  status: string | null
  statusLabel: string | null
  weight: number | string | null
  volume: number | string | null
  packageCount: number | null
  currencyCode: string | null
  customerName: string | null
  agencyName: string | null
  lineCount: number
  serviceCount: number
  createdAt: string
  updatedAt: string
}

/**
 * Filtres acceptés par `ListOrderRequest`.
 *
 * Ni `priority` ni plage de dates : le §10 du prompt corrigé les écarte
 * explicitement, faute de support serveur.
 */
export interface OrderFilters {
  page: number
  perPage: number
  search?: string
  status?: string
  source?: string
  customerId?: string
  agencyId?: string
  depotId?: string
  orderType?: string
  requestedDate?: string
  city?: string
  fromCatalog?: boolean
  /**
   * Commandes qui n'ont pas encore de depot.
   *
   * C'est l'etat dans lequel arrive une commande importee : l'agence est
   * choisie a l'import, le depot reste a affecter. Le filtre les rassemble.
   */
  withoutDepot?: boolean
  sort?: string
  direction?: 'asc' | 'desc'
}
