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
  code: string
  name: string
}

/**
 * Réponse de `/auth/me` : qui, pour quel fournisseur, avec quels droits.
 * Les permissions servent à masquer, jamais à protéger : le serveur décide.
 */
export interface ProviderContext {
  user: {
    id: string
    firstName: string
    lastName: string
    email: string
  }
  provider: {
    id: string
    code: string
    name: string
  }
  carrier: { name: string | null }
  roles: RoleSummary[]
  permissions: string[]
}

/** Une entrée de menu, décidée par le serveur (`/navigation`). */
export interface NavigationEntry {
  key: string
  label: string
  path: string
}
