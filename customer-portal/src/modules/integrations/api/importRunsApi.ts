import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

import type { ImportRun, ImportRunFilters } from '../importRunTypes'

/** L'historique des imports du client courant : le backend impose le client. */
export const importRunsApi = {
  list: (filters: ImportRunFilters) => api.get<Paginated<ImportRun>>('/integrations/import-runs', { query: { ...filters, perPage: filters.perPage ?? 20 } }),
  get: (id: string) => api.get<ApiEnvelope<ImportRun>>(`/integrations/import-runs/${id}`).then((response) => response.data),
}
