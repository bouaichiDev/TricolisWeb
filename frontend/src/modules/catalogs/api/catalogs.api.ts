import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Catalog, CatalogFilters, CatalogItem, CatalogItemFilters } from '../types/catalog'

/** Charge utile relevée sur `StoreCatalogRequest`. */
export interface CatalogPayload {
  code: string
  name: string
  description?: string | null
  status?: string
}

/** Charge utile relevée sur `StoreCatalogItemRequest`. */
export interface CatalogItemPayload {
  articleCode: string
  barcode?: string | null
  name: string
  description?: string | null
  weight?: number
  volume?: number
  length?: number | null
  width?: number | null
  height?: number | null
  status?: string
}

/**
 * Toutes les routes sont imbriquées sous le client.
 *
 * Il n'existe **pas** de `GET /catalogs` global : un catalogue appartient à un
 * client, et c'est ce qui garantit qu'on ne peut pas en consulter un sans
 * savoir de qui il est. Le §14 du prompt corrigé interdit d'en inventer une.
 */
export const catalogsApi = {
  list: (customerId: string, filters: CatalogFilters) =>
    api.get<ApiCollection<Catalog>>(`/customers/${customerId}/catalogs`, {
      query: { ...filters },
    }),

  get: (customerId: string, catalogId: string) =>
    api
      .get<ApiResource<Catalog>>(`/customers/${customerId}/catalogs/${catalogId}`)
      .then((response) => response.data),

  create: (customerId: string, payload: CatalogPayload) =>
    api
      .post<ApiResource<Catalog>>(`/customers/${customerId}/catalogs`, payload)
      .then((response) => response.data),

  update: (customerId: string, catalogId: string, payload: Partial<CatalogPayload>) =>
    api
      .patch<ApiResource<Catalog>>(`/customers/${customerId}/catalogs/${catalogId}`, payload)
      .then((response) => response.data),

  remove: (customerId: string, catalogId: string) =>
    api.delete<void>(`/customers/${customerId}/catalogs/${catalogId}`),

  items: (customerId: string, catalogId: string, filters: CatalogItemFilters) =>
    api.get<ApiCollection<CatalogItem>>(
      `/customers/${customerId}/catalogs/${catalogId}/items`,
      { query: { ...filters } },
    ),

  createItem: (customerId: string, catalogId: string, payload: CatalogItemPayload) =>
    api
      .post<ApiResource<CatalogItem>>(
        `/customers/${customerId}/catalogs/${catalogId}/items`,
        payload,
      )
      .then((response) => response.data),

  updateItem: (
    customerId: string,
    catalogId: string,
    itemId: string,
    payload: Partial<CatalogItemPayload>,
  ) =>
    api
      .patch<ApiResource<CatalogItem>>(
        `/customers/${customerId}/catalogs/${catalogId}/items/${itemId}`,
        payload,
      )
      .then((response) => response.data),

  removeItem: (customerId: string, catalogId: string, itemId: string) =>
    api.delete<void>(`/customers/${customerId}/catalogs/${catalogId}/items/${itemId}`),
}
