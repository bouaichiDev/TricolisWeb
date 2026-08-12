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

/** Contact rattaché à une adresse — relevé sur `AddressContactResource`. */
export interface AddressContact {
  id: string
  addressId: string
  contactId: string
  contactRole: string | null
  isPrimary: boolean
  contact: {
    id: string
    firstName: string
    lastName: string
    phone: string | null
    mobile: string | null
    email: string | null
  }
}

export const addressesApi = {
  list: (params: { page?: number; perPage?: number; search?: string; status?: string }) =>
    api.get<ApiCollection<Address>>('/addresses', { query: params }),

  /**
   * Adresses d'une entité, avec leurs liaisons.
   *
   * `entityType` / `entityId` sont acceptés par `GET /addresses` : c'est le
   * seul moyen de savoir quelles adresses appartiennent à un client, et la
   * réponse porte alors le type de chaque liaison.
   */
  listForEntity: (entityType: AddressEntityType, entityId: string) =>
    api.get<ApiCollection<Address>>('/addresses', {
      query: { entityType, entityId, perPage: 100 },
    }),

  /** Contacts rattachés à une adresse. Réponse non paginée. */
  contacts: (addressId: string) =>
    api
      .get<ApiResource<AddressContact[]>>(`/addresses/${addressId}/contacts`)
      .then((response) => response.data),

  attachContact: (
    addressId: string,
    payload: { contactId: string; contactRole?: string; isPrimary?: boolean },
  ) =>
    api
      .post<ApiResource<AddressContact>>(`/addresses/${addressId}/contacts`, payload)
      .then((response) => response.data),

  detachContact: (addressId: string, linkId: string) =>
    api.delete<void>(`/addresses/${addressId}/contacts/${linkId}`),

  get: (id: string) =>
    api.get<ApiResource<Address>>(`/addresses/${id}`).then((response) => response.data),

  create: (payload: AddressCreatePayload) =>
    api.post<ApiResource<Address>>('/addresses', payload).then((response) => response.data),

  update: (id: string, payload: Partial<AddressPayload>) =>
    api.patch<ApiResource<Address>>(`/addresses/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/addresses/${id}`),
}
