/** Organisation — champs relevés sur `OrganizationResource`. */
export interface Organization {
  id: string
  code: string
  name: string
  legalName: string | null
  registrationNumber: string | null
  taxNumber: string | null
  email: string | null
  phone: string | null
  preferredLanguage: string | null
  timezone: string | null
  currencyCode: string | null
  status: string
  settings: Record<string, unknown> | null
  createdAt: string
  updatedAt: string
}

/** Valeurs de `OrganizationStatus` côté API. */
export const ORGANIZATION_STATUSES = ['pending', 'active', 'suspended', 'closed'] as const

/** Colonnes acceptées par `getSort()` dans `OrganizationController::index`. */
export const ORGANIZATION_SORTABLE = ['name', 'code', 'created_at'] as const

export interface OrganizationFilters {
  page: number
  perPage: number
  search?: string
  status?: string
  sort: string
  direction: 'asc' | 'desc'
}
