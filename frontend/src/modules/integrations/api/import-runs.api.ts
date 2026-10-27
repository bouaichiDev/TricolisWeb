import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { ImportRun } from '../types/importRun'

const base = (configurationId: string) => `/customer-import-configurations/${configurationId}/runs`

/** L'historique d'une configuration d'import : liste paginée, puis la fiche avec les refus. */
export const importRunsApi = {
  list: (configurationId: string, page: number) =>
    api.get<ApiCollection<ImportRun>>(base(configurationId), { query: { page, perPage: 10 } }),

  get: (configurationId: string, runId: string) =>
    api.get<ApiResource<ImportRun>>(`${base(configurationId)}/${runId}`).then((response) => response.data),
}
