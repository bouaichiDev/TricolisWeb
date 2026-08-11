import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { CustomerSite } from '../types/customerSite'

export interface CustomerSitePayload {
  addressId: string
  code: string
  name: string
  siteType?: string | null
  isDefault?: boolean
  status?: string
}

/** Toutes les routes sont imbriquees sous le client : il porte le perimetre. */
export const customerSitesApi = {
  list: (customerId: string, params: { page?: number; perPage?: number; search?: string }) =>
    api.get<ApiCollection<CustomerSite>>(`/customers/${customerId}/sites`, { query: params }),

  get: (customerId: string, siteId: string) =>
    api
      .get<ApiResource<CustomerSite>>(`/customers/${customerId}/sites/${siteId}`)
      .then((response) => response.data),

  create: (customerId: string, payload: CustomerSitePayload) =>
    api
      .post<ApiResource<CustomerSite>>(`/customers/${customerId}/sites`, payload)
      .then((response) => response.data),

  update: (customerId: string, siteId: string, payload: Partial<CustomerSitePayload>) =>
    api
      .patch<ApiResource<CustomerSite>>(`/customers/${customerId}/sites/${siteId}`, payload)
      .then((response) => response.data),

  remove: (customerId: string, siteId: string) =>
    api.delete<void>(`/customers/${customerId}/sites/${siteId}`),
}
