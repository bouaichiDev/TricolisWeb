import type { ListParams } from '@/shared/api/types'

/**
 * Intégrations d'un client — sens entrant et sens sortant.
 *
 * Deux entités portent le mot « API » et ne désignent pas la même chose. Le §19
 * les sépare explicitement :
 *
 * - `CustomerApiConfiguration` (ici) : **le client nous appelle**. Il détient
 *   une clé, nous en gardons le hash, et nous vérifions son IP.
 * - `ApiConfiguration` (`types/apiConfiguration.ts`, phase 5) : **nous appelons
 *   un tiers**. La télématique d'un organisme, par exemple.
 *
 * Le préfixe `Customer` marque le premier sens partout dans ce module.
 */

/**
 * Configuration d'import d'un client — `ImportConfigurationResource`.
 *
 * `mapping` et `validationRules` sont des objets **libres** : le serveur les
 * valide comme des tableaux bornés à 500 entrées, sans imposer de structure.
 * Ils ne sont jamais interprétés — aucune Action ne les exécute.
 *
 * Il n'existe **aucun moteur d'import** : ni `Import`, ni `ImportRow`, ni
 * `ImportError`. Cette configuration décrit comment lire un fichier, elle ne
 * garde pas trace de lectures passées.
 */
export interface CustomerImportConfiguration {
  id: string
  customerId: string
  name: string
  /** Chaîne libre : aucune énumération ni table ne la contraint. */
  sourceType: string
  /** Chaîne libre également, à ne pas confondre avec `ExportFormat`. */
  fileFormat: string
  mapping: Record<string, unknown> | null
  validationRules: Record<string, unknown> | null
  isActive: boolean
}

export interface CustomerImportConfigurationPayload {
  customerId: string
  name: string
  sourceType: string
  fileFormat: string
  mapping?: Record<string, unknown> | null
  validationRules?: Record<string, unknown> | null
  isActive?: boolean
}

/** `IntegrationListQuery`, profil `import`. */
export const IMPORT_CONFIGURATION_SORTS = [
  'name',
  'source_type',
  'file_format',
  'is_active',
] as const

export interface CustomerImportConfigurationFilters extends ListParams {
  customerId?: string
  sourceType?: string
  fileFormat?: string
  isActive?: boolean
}

/**
 * Accès API d'un client — `ApiConfigurationResource`.
 *
 * **`apiKeyHash` n'y figure pas**, et ne doit jamais y figurer : le §75 exige
 * de corriger le backend si un `GET` le renvoyait. La clé elle-même n'existe en
 * clair qu'une fois, à la création et à la rotation.
 *
 * `allowedIps` est une liste d'adresses ou de plages CIDR — la règle serveur
 * `IpOrCidr` valide chaque entrée. `permissions` est une liste de **codes de
 * permissions RBAC existants** : le backend réutilise le référentiel plutôt que
 * d'entretenir un second système.
 */
export interface CustomerApiConfiguration {
  id: string
  customerId: string
  name: string
  allowedIps: string[] | null
  permissions: string[] | null
  isActive: boolean
  /** Posé par le serveur à chaque appel du client. Jamais modifiable ici. */
  lastUsedAt: string | null
}

export interface CustomerApiConfigurationPayload {
  customerId: string
  name: string
  allowedIps?: string[] | null
  permissions?: string[] | null
  isActive?: boolean
}

/**
 * Réponse de création et de rotation — `ApiKeyIssuedResource`.
 *
 * La seule forme où une clé circule en clair. Elle est affichée une fois, puis
 * l'objet est jeté : ni cache, ni stockage, ni URL, ni journal (§22).
 */
export interface CustomerApiKeyIssued {
  configuration: CustomerApiConfiguration
  apiKey: string
  warning: string
}

/** `IntegrationListQuery`, profil `api`. */
export const API_CONFIGURATION_SORTS = ['name', 'is_active', 'last_used_at'] as const

export interface CustomerApiConfigurationFilters extends ListParams {
  customerId?: string
  isActive?: boolean
  lastUsedFrom?: string
  lastUsedTo?: string
}

/**
 * Ce qu'une correspondance produirait sur un fichier donné.
 *
 * Rendu par `POST /customer-import-configurations/{id}/preview`. **Rien n'est
 * créé** : le fichier est lu en mémoire et oublié. C'est ce qui permet de
 * vérifier une correspondance avant de s'en servir — sans quoi on la saisit à
 * l'aveugle.
 */
export interface ImportPreview {
  /** Lignes lues dans le fichier. */
  rowCount: number
  /** Colonnes trouvées : de quoi repérer un nom mal orthographié. */
  columns: string[]
  /** La charge utile que la correspondance construit. */
  payload: Record<string, unknown>
  /** Ce qui manquerait, selon les règles réelles de création d'une commande. */
  errors: Record<string, string[]>
  /**
   * Identifiants qu'aucun fichier client ne porte, et que le verdict n'exige
   * donc pas. C'est le travail que devra faire un futur moteur d'import.
   */
  resolvedElsewhere: string[]
}

/**
 * Ce qu'un import a réellement créé.
 *
 * Rendu par `POST /customer-import-configurations/{id}/import`. Contrairement à
 * la prévisualisation, **les commandes existent** : elles se retrouvent dans la
 * liste, filtrées sur leur origine.
 */
export interface ImportResult {
  rowCount: number
  orders: { id: string; orderNumber: string; externalReference: string | null }[]
}
