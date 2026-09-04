/**
 * Une API externe appelée par l'organisme —
 * `OrganizationApiConfigurationResource`.
 *
 * Sens inverse des accès API client, où un client détient une clé pour nous
 * appeler. Ici c'est nous qui appelons : la position d'un chauffeur, rendue par
 * la télématique de l'organisme.
 *
 * **Le secret n'y figure pas.** `hasCredentials` dit qu'il est posé ; il ne
 * ressort jamais, et il n'y a pas de raison de le relire — on le remplace.
 */
export interface ApiConfiguration {
  id: string
  organizationId: string
  code: string
  name: string
  baseUrl: string
  authType: AuthType
  hasCredentials: boolean
  headers: Record<string, string> | null
  settings: ApiCallSettings | null
  timeoutSeconds: number
  isActive: boolean
  lastUsedAt: string | null
  createdAt: string
  updatedAt: string
}

/**
 * La forme de l'appel, décrite en base plutôt qu'en code.
 *
 * Deux jetons sont remplacés par le serveur : `{reference}` — la référence de
 * la course chez le fournisseur — et `{limit}`. Le canal de l'organisme, lui,
 * est fixe et fait partie du chemin.
 */
export interface ApiCallSettings {
  path?: string | null
  queryKey?: string | null
  queryTemplate?: string | null
}

export const AUTH_TYPES = ['none', 'bearer', 'api_key', 'basic'] as const

export type AuthType = (typeof AUTH_TYPES)[number]

export interface ApiConfigurationFilters {
  page: number
  perPage: number
  search?: string
}

/**
 * Charge utile.
 *
 * `credentials` est en écriture seule : omis, le secret en place est conservé ;
 * envoyé à `null`, il est effacé.
 */
export interface ApiConfigurationPayload {
  code: string
  name: string
  baseUrl: string
  authType: string
  credentials?: string | null
  settings?: ApiCallSettings | null
  timeoutSeconds?: number
  isActive?: boolean
}
