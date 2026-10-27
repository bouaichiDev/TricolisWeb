/**
 * Le module Intégrations vu par le client (Phase 6). Aucune donnée de
 * connexion n'y figure : ni hôte, ni identifiant, ni secret — le backend ne
 * les envoie pas.
 */

export interface MappingPair {
  target: string
  source: string | null
  constant: string | number | boolean | null
  type: string | null
}

export type ImportChannel = 'upload' | 'rest_pull' | 'sftp' | 'ftp'

export interface ImportIntegration {
  id: string
  name: string
  sourceType: string
  fileFormat: string
  channel: ImportChannel
  frequency: 'manual' | 'hourly'
  isActive: boolean
  lastRunAt: string | null
  actions: { canUpload: boolean }
  /** Présente avec la permission `customer.integrations.mapping.view`. */
  mapping: MappingPair[] | null
}

export interface ExportIntegration {
  id: string
  name: string
  exportType: string
  format: string | null
  transport: string | null
  frequency: string
  isActive: boolean
  lastSentAt: string | null
}

export interface ApiAccess {
  id: string
  name: string
  isActive: boolean
  permissions: string[]
  restrictedByIp: boolean
  lastUsedAt: string | null
}

export interface IntegrationsOverview {
  imports: ImportIntegration[]
  exports: ExportIntegration[]
  apiAccesses: ApiAccess[]
  failedExportJobs: number
}

export type ExportJobStatus = 'pending' | 'processing' | 'sent' | 'failed'

export interface ExportJob {
  id: string
  configuration: { id: string; name: string; exportType: string; transport: string | null } | null
  entityType: string | null
  entityReference: string | null
  fileName: string | null
  status: ExportJobStatus
  attemptCount: number
  generatedAt: string | null
  sentAt: string | null
  errorMessage: string | null
  actions: { canRetry: boolean }
}

export type PreviewOrderStatus = 'valid' | 'invalid' | 'duplicate'

export interface PreviewOrder {
  index: number
  reference: string | null
  status: PreviewOrderStatus
  duplicateAction: 'skip' | 'reject' | 'update' | null
  lineCount: number
  serviceCount: number
  errors: Record<string, string[]>
}

export interface ImportPreview {
  token: string
  expiresAt: string
  fileName: string
  configuration: { id: string; name: string }
  rowCount: number
  headers: string[]
  sampleRows: Record<string, string | number | boolean | null>[]
  mapping: MappingPair[]
  summary: { orderCount: number; valid: number; invalid: number; duplicates: number; orders: PreviewOrder[] }
}

export type OutcomeStatus = 'created' | 'updated' | 'skipped_duplicate' | 'invalid' | 'failed'

export interface ImportOutcome {
  index: number
  reference: string | null
  status: OutcomeStatus
  orderId: string | null
  orderNumber: string | null
  errors: Record<string, string[]>
}

export interface ImportResult {
  status: 'completed' | 'queued' | 'running' | 'failed'
  token?: string
  fileName?: string
  message?: string
  rowCount?: number
  orderCount?: number
  created?: number
  updated?: number
  skipped?: number
  invalid?: number
  failed?: number
  valid?: number
  outcomes?: ImportOutcome[]
  errors?: ImportOutcome[]
}
