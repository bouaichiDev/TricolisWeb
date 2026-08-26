/**
 * Chauffeur — `DriverListResource` / `DriverDetailResource`.
 *
 * Le schéma réel porte un **nom unique**, pas un prénom et un nom séparés, et
 * ne rattache pas de compte utilisateur. Il rattache en revanche une adresse et
 * un contact, comme le fournisseur. Écart consigné dans
 * `docs/frontend/phase-4-analysis.md`.
 */
export interface Driver {
  id: string
  organizationId: string
  providerId: string
  addressId: string | null
  contactId: string | null
  code: string
  name: string
  status: string
  providerName?: string
}

export interface DriverPayload {
  providerId: string
  code: string
  name: string
  status: string
  addressId?: string | null
  contactId?: string | null
}

export interface DriverFilters {
  page: number
  perPage: number
  search?: string
  status?: string
  providerId?: string
  sort?: string
  direction?: 'asc' | 'desc'
}
