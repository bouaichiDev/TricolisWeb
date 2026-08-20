/**
 * Catalogue d'un client — `CatalogResource`.
 *
 * Ni `version` ni `validFrom` / `validUntil` : le premier prompt les demandait,
 * la ressource ne les expose pas et le §52 du prompt corrigé interdit de les
 * afficher.
 *
 * `items` n'est chargé que par `show` ; la liste ne renvoie que `itemCount`.
 */
export interface Catalog {
  id: string
  customerId: string
  code: string
  name: string
  description: string | null
  status: string
  itemCount: number
  items?: CatalogItem[]
  createdAt: string
  updatedAt: string
}

/**
 * Article d'un catalogue — `CatalogItemResource`.
 *
 * Pas de `SKU` ni d'`unit` : le §12 du prompt corrigé les nomme explicitement
 * comme à ne pas inventer. `articleCode` tient le rôle de référence article.
 */
export interface CatalogItem {
  id: string
  catalogId: string
  articleCode: string
  barcode: string | null
  name: string
  description: string | null
  weight: number | string | null
  volume: number | string | null
  length: number | string | null
  width: number | string | null
  height: number | string | null
  /** Minutes de montage, `null` quand l'article n'en demande pas. */
  assemblyTimeMinutes: number | null
  status: string
  createdAt: string
  updatedAt: string
}

export const CATALOG_STATUSES = ['active', 'inactive'] as const

export interface CatalogFilters {
  page: number
  perPage: number
  search?: string
  status?: string
}

export interface CatalogItemFilters {
  page: number
  perPage: number
  search?: string
  status?: string
}
