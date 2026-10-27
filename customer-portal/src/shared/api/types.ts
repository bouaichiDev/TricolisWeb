/** Enveloppe standard des réponses de l'API. */
export interface ApiEnvelope<T> {
  data: T
  meta: Record<string, unknown>
}

export interface Paginated<T> {
  data: T[]
  meta: {
    currentPage: number
    perPage: number
    total: number
    lastPage: number
  }
}

export interface RoleSummary {
  id: string
  code: string
  name: string
}

export interface CustomerFeatures {
  catalog: boolean
  stock: boolean
  tracking: boolean
  packages: boolean
  appointments: boolean
}

/** Réponse de `/context` : qui, pour quel client, avec quels droits. */
export interface CustomerContext {
  user: {
    id: string
    firstName: string
    lastName: string
    email: string
    phone: string | null
    preferredLanguage: string
  }
  customer: {
    id: string
    code: string
    name: string
    legalName: string | null
    email: string | null
    phone: string | null
    features: CustomerFeatures
  }
  carrier: { id: string; name: string | null }
  membership: { id: string; isOwner: boolean; status: string }
  roles: RoleSummary[]
  permissions: string[]
}

export type MemberStatus = 'invited' | 'active' | 'suspended' | 'disabled'

export interface PortalUser {
  id: string
  status: MemberStatus
  statusLabel: string
  isOwner: boolean
  invitedAt: string | null
  activatedAt: string | null
  disabledAt: string | null
  user: {
    id: string
    firstName: string
    lastName: string
    email: string
    phone: string | null
    lastLoginAt: string | null
  }
  roles: RoleSummary[]
}

export interface PortalRole extends RoleSummary {
  permissions: string[]
}
