import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Customer, CustomerFilters } from '../types/customer'

export interface CustomerPayload {
  code: string
  name: string
  legalName?: string | null
  email?: string | null
  phone?: string | null
  paymentMode?: string | null
  communicationMode?: string | null
  catalogEnabled?: boolean
  stockEnabled?: boolean
  packageEnabled?: boolean
  appointmentEnabled?: boolean
  trackingEnabled?: boolean
  status: string
}

export const customersApi = {
  list: (filters: CustomerFilters) =>
    api.get<ApiCollection<Customer>>('/customers', { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<Customer>>(`/customers/${id}`).then((response) => response.data),

  create: (payload: CustomerPayload) =>
    api.post<ApiResource<Customer>>('/customers', payload).then((response) => response.data),

  update: (id: string, payload: Partial<CustomerPayload>) =>
    api.patch<ApiResource<Customer>>(`/customers/${id}`, payload).then((response) => response.data),

  /**
   * Changement de statut.
   *
   * Route distincte du `PATCH` general : passer a `blocked` exige la permission
   * `customers.block`, les autres transitions `customers.update`.
   */
  changeStatus: (id: string, status: string) =>
    api
      .patch<ApiResource<Customer>>(`/customers/${id}/status`, { status })
      .then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/customers/${id}`),
}
