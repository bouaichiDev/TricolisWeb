import type { ListParams } from '@/shared/api/types'

/**
 * Une destination d'export d'un client.
 *
 * **Le mot de passe n'en fait pas partie.** Le serveur ne rend jamais que
 * `hasPassword` : le §124 interdit de renvoyer un secret, fût-ce à celui qui l'a
 * saisi. Le champ de saisie sert donc à le remplacer, jamais à le relire.
 */
export interface ExportConfiguration {
  id: string
  customerId: string
  name: string
  exportType: string
  format: string
  transport: string
  host: string | null
  port: number | null
  username: string | null
  hasPassword: boolean
  remoteDirectory: string | null
  fileNamePattern: string | null
  encoding: string | null
  frequency: string | null
  settings: Record<string, unknown> | null
  isActive: boolean
  jobCount?: number
}

export interface ExportConfigurationPayload {
  customerId: string
  name: string
  exportType: string
  format: string
  transport: string
  host?: string | null
  port?: number | null
  username?: string | null
  /** Absent = inchangé. Une chaîne vide effacerait le secret sans le vouloir. */
  password?: string
  remoteDirectory?: string | null
  fileNamePattern?: string | null
  encoding?: string | null
  frequency?: string | null
  settings?: Record<string, unknown> | null
  isActive?: boolean
}

/** Un envoi : une facture, une destination, et ce qui s'est passé. */
export interface ExportJob {
  id: string
  customerId: string
  configurationId: string
  entityType: string | null
  entityId: string | null
  fileName: string | null
  hasFile: boolean
  status: string
  attemptCount: number
  generatedAt: string | null
  sentAt: string | null
  errorMessage: string | null
  configuration?: { id: string; name: string; format: string; transport: string }
}

export interface ExportJobFilters extends ListParams {
  status?: string
  customerId?: string
  configurationId?: string
  generatedFrom?: string
  generatedTo?: string
}

/**
 * Ce qu'on sait réellement produire et transmettre pour une facture.
 *
 * Les quatre formats et les cinq transports du modèle sont proposés, parce que
 * chacun a désormais son générateur et son transporteur. Le §32 n'autorisait
 * pas à en offrir davantage : `xlsx`, `edi` ou `s3` ne figurent pas au
 * diagramme et n'apparaissent donc pas.
 */
export const INVOICE_FORMATS = ['json', 'xml', 'csv', 'pdf'] as const
export const INVOICE_TRANSPORTS = ['rest_api', 'ftp', 'sftp', 'email', 'manual'] as const

/**
 * Les portes par lesquelles une API cliente se laisse appeler.
 *
 * Le secret, lui, reste dans le champ mot de passe — chiffré côté serveur et
 * jamais rendu. Ces modes ne disent que *comment* il est présenté.
 */
export const AUTH_MODES = ['none', 'bearer', 'basic', 'api_key', 'oauth2'] as const

export type AuthMode = (typeof AUTH_MODES)[number]

/** Les réglages que la plateforme sait interpréter ; le reste passe tel quel. */
export interface ExportSettings {
  authType?: AuthMode
  apiKeyHeader?: string
  tokenUrl?: string
  clientId?: string
  scope?: string
  recipients?: string
  subject?: string
  body?: string
  delimiter?: string
  enclosure?: string
  documentTitle?: string
  [key: string]: unknown
}

/** Le type d'export et la fréquence que la clôture consulte. */
export const INVOICE_EXPORT_TYPE = 'invoice'
export const ON_INVOICE_CLOSED = 'on_invoice_closed'

/** Les transports qui exigent un hôte — le serveur applique la même règle. */
export function needsHost(transport: string): boolean {
  return transport === 'ftp' || transport === 'sftp' || transport === 'rest_api'
}

/** Le nom du secret change avec le mode : jeton, clé, ou mot de passe. */
export function secretLabelKey(transport: string, mode: AuthMode): string {
  if (transport !== 'rest_api') {
    return 'password'
  }

  return mode === 'oauth2' || mode === 'bearer' ? 'token' : mode === 'api_key' ? 'apiKey' : 'password'
}

/**
 * Formats et transports du modèle — les seuls.
 *
 * `ExportFormat` et `ExportTransport` sont des énumérations PHP, et elles ne
 * contiennent rien d'autre : ni `xlsx`, ni `txt`, ni `zip`, ni `edi` côté
 * format ; ni `ftps`, ni `http`, ni `s3`, ni `webdav` côté transport. Les §37,
 * §38, §80 et §81 le disent, et les enums le confirment.
 *
 * Les valeurs sont en **minuscules** : c'est ce que la base stocke. Le prompt
 * les écrit en majuscules, ce sont les mêmes.
 *
 * `INVOICE_FORMATS` et `INVOICE_TRANSPORTS` couvrent la facture ; ces deux
 * listes-ci sont le modèle complet, employé par les écrans d'intégration.
 */
export const EXPORT_FORMATS = ['xml', 'csv', 'json', 'pdf'] as const
export const EXPORT_TRANSPORTS = ['ftp', 'sftp', 'rest_api', 'email', 'manual'] as const

export type ExportFormat = (typeof EXPORT_FORMATS)[number]
export type ExportTransport = (typeof EXPORT_TRANSPORTS)[number]

/** `IntegrationListQuery`, profil `export`. */
export const EXPORT_CONFIGURATION_SORTS = [
  'name',
  'export_type',
  'format',
  'transport',
  'frequency',
  'is_active',
] as const

export interface ExportConfigurationFilters extends ListParams {
  customerId?: string
  exportType?: string
  format?: string
  transport?: string
  frequency?: string
  isActive?: boolean
}

/** `IntegrationListQuery`, profil `job`. */
export const EXPORT_JOB_SORTS = [
  'generated_at',
  'sent_at',
  'attempt_count',
  'status',
  'file_name',
] as const

/**
 * Déclenchement manuel d'un envoi — `StoreExportJobRequest`.
 *
 * `entityType` est facultatif mais, dès qu'il est fourni, `entityId` devient
 * obligatoire et le type doit figurer dans `MorphMap::registered()`. Jamais un
 * nom de classe PHP (§51).
 */
export interface ExportJobPayload {
  configurationId: string
  entityType?: string | null
  entityId?: string | null
  status: string
}

/**
 * Un envoi déjà transmis ne se relance pas.
 *
 * `RetryExportJobAction` refuse quand `sentAt` est renseignée — contrôlé avant
 * la transaction puis sous verrou. Le bouton disparaît alors, mais c'est le 409
 * qui fait autorité.
 */
export function isRetryable(job: Pick<ExportJob, 'sentAt'>): boolean {
  return job.sentAt === null
}
