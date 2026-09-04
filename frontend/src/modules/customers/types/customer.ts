/**
 * Client — champs releves sur `CustomerResource`, pas deduits du diagramme.
 *
 * Les cinq capacites sont exactement celles que l'API expose : aucune inventee,
 * aucune omise.
 */
export interface Customer {
  id: string
  organizationId: string
  code: string
  name: string
  legalName: string | null
  email: string | null
  phone: string | null
  paymentMode: string | null
  communicationMode: string | null
  catalogEnabled: boolean
  stockEnabled: boolean
  packageEnabled: boolean
  appointmentEnabled: boolean
  trackingEnabled: boolean
  status: string
  createdAt: string
  updatedAt: string
}

/** Les cinq capacites, dans l'ordre d'affichage des maquettes. */
export const CUSTOMER_CAPABILITIES = [
  'catalogEnabled',
  'stockEnabled',
  'packageEnabled',
  'appointmentEnabled',
  'trackingEnabled',
] as const

export type CustomerCapability = (typeof CUSTOMER_CAPABILITIES)[number]

/** Filtres acceptes par `CustomerListQuery`. Rien de plus. */
export interface CustomerFilters {
  page?: number
  perPage?: number
  search?: string
  status?: string
  sort?: string
  direction?: 'asc' | 'desc'
}

/** Colonnes triables cote serveur ; toute autre valeur renvoie 422. */
export const CUSTOMER_SORTABLE = ['name', 'code', 'created_at'] as const
