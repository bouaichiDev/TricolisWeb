/** Le catalogue du client : ce qu'il fait transporter, pas ce qu'il a en stock. */

export interface Catalog {
  id: string
  code: string
  name: string
  status: string
  isActive: boolean
  itemsCount: number
}

export interface CatalogItem {
  id: string
  catalog?: { id: string; code: string; name: string }
  articleCode: string
  barcode: string | null
  name: string
  description: string | null
  /** Décimaux en chaînes, tels que stockés : jamais recalculés en flottant. */
  weight: string | null
  volume: string | null
  length: string | null
  width: string | null
  height: string | null
  assemblyTimeMinutes: number | null
  status: string
  isActive: boolean
  createdAt: string | null
  /** Fiche seulement : lignes de commande et articles de stock qui l'utilisent. */
  usage?: { orderLines: number; stockItems: number }
  /** Ce que l'utilisateur peut faire de l'article ; l'API revérifie. */
  actions?: { canUpdate: boolean }
}

export interface CatalogFilters {
  search?: string
  catalogId?: string
  status?: 'active' | 'inactive'
  sort?: 'articleCode' | 'name' | 'createdAt'
  direction?: 'asc' | 'desc'
  page?: number
  perPage?: number
}

/** Modification : jamais le catalogue ni le statut (`activate` / `deactivate`). */
export type CatalogItemUpdatePayload = Omit<CatalogItemPayload, 'catalogId'>

export interface CatalogItemPayload {
  catalogId: string
  articleCode: string
  name: string
  barcode: string | null
  description: string | null
  weight: string | null
  volume: string | null
}
