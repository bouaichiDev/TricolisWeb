import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

import type { Catalog, CatalogFilters, CatalogItem, CatalogItemPayload, CatalogItemUpdatePayload } from '../types'

const data = <T>(promise: Promise<ApiEnvelope<T>>) => promise.then((response) => response.data)

/** Le catalogue du client courant : jamais d'identifiant de client envoyé. */
export const catalogApi = {
  catalogs: () => data(api.get<ApiEnvelope<Catalog[]>>('/catalog/catalogs')),
  items: (filters: CatalogFilters) => api.get<Paginated<CatalogItem>>('/catalog/items', { query: { ...filters, perPage: filters.perPage ?? 20 } }),
  item: (id: string) => data(api.get<ApiEnvelope<CatalogItem>>(`/catalog/items/${id}`)),
  create: (payload: CatalogItemPayload) => data(api.post<ApiEnvelope<CatalogItem>>('/catalog/items', payload)),
  update: ({ id, ...payload }: CatalogItemUpdatePayload & { id: string }) => data(api.patch<ApiEnvelope<CatalogItem>>(`/catalog/items/${id}`, payload)),
  setActive: ({ id, active }: { id: string; active: boolean }) =>
    data(api.post<ApiEnvelope<CatalogItem>>(`/catalog/items/${id}/${active ? 'activate' : 'deactivate'}`)),
}
