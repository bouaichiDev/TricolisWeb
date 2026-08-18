import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Service, ServiceFilters } from '../types/service'

/**
 * Charge utile relevée sur `StoreServiceRequest`.
 *
 * `unit`, `defaultDurationMinutes` et les quatre drapeaux sont `required` :
 * le formulaire doit les demander, aucun n'a de valeur implicite côté API.
 */
export interface ServicePayload {
  code: string
  name: string
  unit: string
  defaultDurationMinutes: number
  billableToCustomer: boolean
  payableToProvider: boolean
  requiresAddress: boolean
  requiresContact: boolean
  status?: string
}

export const servicesApi = {
  list: (filters: ServiceFilters) =>
    api.get<ApiCollection<Service>>('/services', { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<Service>>(`/services/${id}`).then((response) => response.data),

  create: (payload: ServicePayload) =>
    api.post<ApiResource<Service>>('/services', payload).then((response) => response.data),

  update: (id: string, payload: Partial<ServicePayload>) =>
    api.patch<ApiResource<Service>>(`/services/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/services/${id}`),
}
