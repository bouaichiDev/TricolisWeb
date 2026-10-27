import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

import type { ExportJob, ExportJobStatus, ImportPreview, ImportResult, IntegrationsOverview } from '../types'

export interface ExportJobFilters {
  status?: ExportJobStatus
  configurationId?: string
  page?: number
  perPage?: number
}

const data = <T>(promise: Promise<ApiEnvelope<T>>) => promise.then((response) => response.data)

/**
 * Intégrations du client courant. Aucun identifiant de client n'est envoyé :
 * le backend l'impose depuis la session.
 */
export const integrationsApi = {
  overview: () => data(api.get<ApiEnvelope<IntegrationsOverview>>('/integrations')),
  exportJobs: (filters: ExportJobFilters) =>
    api.get<Paginated<ExportJob>>('/integrations/export-jobs', { query: { ...filters, perPage: filters.perPage ?? 20 } }),
  retryExportJob: (id: string) => data(api.post<ApiEnvelope<ExportJob>>(`/integrations/export-jobs/${id}/retry`)),
  preview: ({ configurationId, file }: { configurationId: string; file: File }) => {
    const body = new FormData()
    body.append('configurationId', configurationId)
    body.append('file', file)

    return data(api.upload<ApiEnvelope<ImportPreview>>('/integrations/imports/preview', body))
  },
  execute: ({ configurationId, token }: { configurationId: string; token: string }) =>
    data(api.post<ApiEnvelope<ImportResult>>('/integrations/imports/execute', { configurationId, token })),
  result: (token: string) => data(api.get<ApiEnvelope<ImportResult>>(`/integrations/imports/${token}`)),
}
