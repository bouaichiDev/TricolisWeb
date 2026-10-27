import type { ExportJobStatus, ImportChannel, OutcomeStatus } from '../types'

/**
 * Libellés du module. Les valeurs techniques (`sourceType`, `exportType`,
 * `frequency`) sont des chaînes libres côté backend : une valeur inconnue
 * s'affiche telle quelle plutôt que d'être devinée.
 */
export const channelLabels: Record<ImportChannel, string> = {
  upload: 'Dépôt de fichier',
  rest_pull: 'API (relève automatique)',
  sftp: 'SFTP (relève automatique)',
  ftp: 'FTP (relève automatique)',
}

export const frequencyLabel = (frequency: string): string =>
  ({
    manual: 'À la demande',
    hourly: 'Toutes les heures',
    on_order_status_changed: 'À chaque changement de statut',
    on_invoice_closed: 'À la clôture de facture',
    daily: 'Quotidien',
  })[frequency] ?? frequency

export const exportTypeLabel = (type: string): string =>
  ({ order_status: 'Statuts de commande', invoice: 'Factures' })[type] ?? type

export const transportLabel = (transport: string | null): string =>
  transport === null
    ? '—'
    : ({ rest_api: 'API REST', sftp: 'SFTP', ftp: 'FTP', email: 'Courriel', manual: 'Manuel' })[transport] ?? transport

export const jobStatusLabels: Record<ExportJobStatus, string> = {
  pending: 'En attente',
  processing: 'En cours',
  sent: 'Transmis',
  failed: 'En échec',
}

export const outcomeLabels: Record<OutcomeStatus, string> = {
  created: 'Créée',
  updated: 'Mise à jour',
  skipped_duplicate: 'Déjà importée',
  invalid: 'Refusée',
  failed: 'Erreur technique',
}

/** Les messages d'une commande, à plat : « lines.0.quantity : … ». */
export const flattenErrors = (errors: Record<string, string[]>): string[] =>
  Object.entries(errors).flatMap(([field, messages]) =>
    messages.map((message) => (field === '_' ? message : `${field.replace(/^orders\.\d+\./, '')} : ${message}`)),
  )
