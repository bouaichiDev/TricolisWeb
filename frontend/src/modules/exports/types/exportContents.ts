import { INVOICE_EXPORT_TYPE, ON_INVOICE_CLOSED } from './export'

/** Le statut de commande poussé à chaque changement (REST Push, Phase 6 portail). */
export const ORDER_STATUS_EXPORT_TYPE = 'order_status'
export const ON_ORDER_STATUS_CHANGED = 'on_order_status_changed'

export type ExportContent = 'invoice' | 'order_status'

/** Chaque contenu fixe son type et son déclencheur ; le statut ne s'écrit qu'en JSON. */
export const EXPORT_CONTENTS: Record<ExportContent, { exportType: string; frequency: string; formats?: readonly string[] }> = {
  invoice: { exportType: INVOICE_EXPORT_TYPE, frequency: ON_INVOICE_CLOSED },
  order_status: { exportType: ORDER_STATUS_EXPORT_TYPE, frequency: ON_ORDER_STATUS_CHANGED, formats: ['json'] },
}

export const contentOf = (exportType?: string | null): ExportContent =>
  exportType === ORDER_STATUS_EXPORT_TYPE ? 'order_status' : 'invoice'
