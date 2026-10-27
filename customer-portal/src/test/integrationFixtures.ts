import type { ExportJob, ImportIntegration, ImportPreview, IntegrationsOverview } from '@/modules/integrations/types'

export function makeImportFlow(overrides: Partial<ImportIntegration> = {}): ImportIntegration {
  return {
    id: '01IMPORT',
    name: 'Commandes Excel',
    sourceType: 'upload',
    fileFormat: 'xlsx',
    channel: 'upload',
    frequency: 'manual',
    isActive: true,
    lastRunAt: null,
    actions: { canUpload: true },
    mapping: null,
    ...overrides,
  }
}

export function makeOverview(overrides: Partial<IntegrationsOverview> = {}): IntegrationsOverview {
  return {
    imports: [makeImportFlow(), makeImportFlow({ id: '01SFTP', name: 'Commandes XML', channel: 'sftp', frequency: 'hourly', fileFormat: 'xml', actions: { canUpload: false } })],
    exports: [{ id: '01EXPORT', name: 'Statuts REST', exportType: 'order_status', format: 'json', transport: 'rest_api', frequency: 'on_order_status_changed', isActive: true, lastSentAt: null }],
    apiAccesses: [{ id: '01API', name: 'ERP', isActive: true, permissions: ['orders.view'], restrictedByIp: true, lastUsedAt: null }],
    failedExportJobs: 1,
    ...overrides,
  }
}

export function makeExportJob(overrides: Partial<ExportJob> = {}): ExportJob {
  return {
    id: '01JOB',
    configuration: { id: '01EXPORT', name: 'Statuts REST', exportType: 'order_status', transport: 'rest_api' },
    entityType: 'order',
    entityReference: 'IKEA-2026-0001',
    fileName: 'order-status.json',
    status: 'failed',
    attemptCount: 1,
    generatedAt: '2026-09-30T10:00:00+02:00',
    sentAt: null,
    errorMessage: 'Le système du client a répondu 503.',
    actions: { canRetry: true },
    ...overrides,
  }
}

export function makePreview(): ImportPreview {
  return {
    token: 'T'.repeat(48),
    expiresAt: '2026-09-30T11:00:00+02:00',
    fileName: 'commandes.xlsx',
    configuration: { id: '01IMPORT', name: 'Commandes Excel' },
    rowCount: 3,
    headers: ['Commande', 'Date', 'Article', 'Quantité'],
    sampleRows: [{ Commande: 'XL-1001', Date: '2026-10-05', Article: 'TAB-01', Quantité: '2' }],
    mapping: [
      { target: 'externalReference', source: 'Commande', constant: null, type: null },
      { target: 'services[].serviceCode', source: null, constant: 'DELIVERY', type: null },
    ],
    summary: {
      orderCount: 2,
      valid: 1,
      invalid: 1,
      duplicates: 0,
      orders: [
        { index: 0, reference: 'XL-1001', status: 'valid', duplicateAction: null, lineCount: 2, serviceCount: 1, errors: {} },
        { index: 1, reference: 'XL-1002', status: 'invalid', duplicateAction: null, lineCount: 1, serviceCount: 1, errors: { 'lines.0.quantity': ['Le champ quantité est obligatoire.'] } },
      ],
    },
  }
}
