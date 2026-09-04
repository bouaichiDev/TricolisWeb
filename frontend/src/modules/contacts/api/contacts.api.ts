import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { AddressEntityType } from '@/modules/addresses/types/address'

/** Contact — champs relevés sur `ContactResource`. */
export interface Contact {
  id: string
  firstName: string
  lastName: string
  phone: string | null
  mobile: string | null
  email: string | null
  preferredLanguage: string | null
  isActive: boolean
}

/**
 * `entityType` / `entityId` créent la liaison `EntityContact` en même temps que
 * le contact : un contact sans rattachement serait invisible dans son
 * organisation, exactement comme une adresse sans liaison.
 */
export interface ContactPayload {
  firstName: string
  lastName: string
  phone?: string | null
  mobile?: string | null
  email?: string | null
  entityType?: AddressEntityType
  entityId?: string
  contactRole?: string
  isPrimary?: boolean
}

export const contactsApi = {
  /** Contacts de l'organisation active ; `GET /contacts` s'en charge. */
  list: (params: { page?: number; perPage?: number; search?: string }) =>
    api.get<ApiCollection<Contact>>('/contacts', { query: params }),

  create: (payload: ContactPayload) =>
    api.post<ApiResource<Contact>>('/contacts', payload).then((response) => response.data),

  update: (id: string, payload: Partial<ContactPayload>) =>
    api.patch<ApiResource<Contact>>(`/contacts/${id}`, payload).then((response) => response.data),
}
