import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Address, AddressEntityType } from '../types/address'

export interface AddressPayload {
  code?: string | null
  name?: string | null
  addressLine1: string
  addressLine2?: string | null
  addressLine3?: string | null
  floor?: string | null
  addressNumber?: string | null
  route?: string | null
  sublocality?: string | null
  postalCode?: string | null
  city?: string | null
  town?: string | null
  country?: string | null
  latitude?: number | null
  longitude?: number | null
  instructions?: string | null
  timeWindowFrom?: string | null
  timeWindowTo?: string | null
  isDefault?: boolean
  status?: string
}

/** `entityType` / `entityId` creent la liaison en meme temps que l'adresse. */
export interface AddressCreatePayload extends AddressPayload {
  entityType?: AddressEntityType
  entityId?: string
  addressType?: string | null
}

export const addressesApi = {
  list: (params: { page?: number; perPage?: number; search?: string; status?: string }) =>
    api.get<ApiCollection<Address>>('/addresses', { query: params }),

  get: (id: string) =>
    api.get<ApiResource<Address>>(`/addresses/${id}`).then((response) => response.data),

  create: (payload: AddressCreatePayload) =>
    api.post<ApiResource<Address>>('/addresses', payload).then((response) => response.data),

  update: (id: string, payload: Partial<AddressPayload>) =>
    api.patch<ApiResource<Address>>(`/addresses/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/addresses/${id}`),
}
