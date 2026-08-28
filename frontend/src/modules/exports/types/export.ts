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
 * Le §32 ne retient CSV et PDF que si leur générateur existe : il n'existe pas,
 * et une destination configurée en CSV échouerait à chaque clôture. Les
 * transports `email` et `manual` n'ont, de même, aucun transporteur de facture.
 * Les proposer serait offrir un choix qui casse plus tard, loin de l'écran.
 */
export const INVOICE_FORMATS = ['json', 'xml'] as const
export const INVOICE_TRANSPORTS = ['rest_api', 'ftp', 'sftp'] as const

/** Le type d'export et la fréquence que la clôture consulte. */
export const INVOICE_EXPORT_TYPE = 'invoice'
export const ON_INVOICE_CLOSED = 'on_invoice_closed'

/** Les transports qui exigent un hôte — le serveur applique la même règle. */
export function needsHost(transport: string): boolean {
  return transport === 'ftp' || transport === 'sftp' || transport === 'rest_api'
}
