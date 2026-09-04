/**
 * Fournisseur — `ProviderListResource` / `ProviderDetailResource`.
 *
 * Le schéma réel ne porte ni `providerType` ni `legacyId`, contrairement à
 * l'ancienne version du diagramme : il rattache une adresse et un contact,
 * comme les autres entités du domaine. L'écart est consigné dans
 * `docs/frontend/phase-4-analysis.md`.
 */
export interface Provider {
  id: string
  organizationId: string
  addressId: string | null
  contactId: string | null
  code: string
  name: string
  status: string
  driverCount?: number
  vehicleCount?: number
  /** Chargees par `GET /providers/{id}` ; absentes de la liste. */
  address?: { id: string; name: string | null; city: string | null; postalCode: string | null } | null
  contact?: { id: string; firstName: string | null; lastName: string | null; email: string | null; phone: string | null } | null
}

export interface ProviderPayload {
  code: string
  name: string
  status: string
  addressId?: string | null
  contactId?: string | null
}

export interface ProviderFilters {
  page: number
  perPage: number
  search?: string
  status?: string
  addressId?: string
  contactId?: string
  sort?: string
  direction?: 'asc' | 'desc'
}
