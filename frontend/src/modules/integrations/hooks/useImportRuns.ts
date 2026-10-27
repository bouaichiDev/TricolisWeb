import { keepPreviousData, useQuery } from '@tanstack/react-query'

import { importRunsApi } from '../api/import-runs.api'

const keys = {
  list: (configurationId: string, page: number) => ['integrations', 'import-runs', configurationId, page] as const,
  detail: (configurationId: string, runId: string) => ['integrations', 'import-run', configurationId, runId] as const,
}

export const useImportRuns = (configurationId: string, page: number) =>
  useQuery({
    queryKey: keys.list(configurationId, page),
    queryFn: () => importRunsApi.list(configurationId, page),
    placeholderData: keepPreviousData,
  })

/** La fiche d'une exécution, chargée seulement quand on l'ouvre. */
export const useImportRun = (configurationId: string, runId: string | null) =>
  useQuery({
    queryKey: keys.detail(configurationId, runId ?? ''),
    queryFn: () => importRunsApi.get(configurationId, runId as string),
    enabled: runId !== null,
  })
