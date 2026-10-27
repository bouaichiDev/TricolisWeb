import { keepPreviousData, useQuery } from '@tanstack/react-query'

import { importRunsApi } from '../api/importRunsApi'
import type { ImportRunFilters } from '../importRunTypes'

export const useImportRuns = (filters: ImportRunFilters) =>
  useQuery({ queryKey: ['integrations', 'import-runs', filters], queryFn: () => importRunsApi.list(filters), placeholderData: keepPreviousData })

/** La fiche d'une exécution, avec les commandes refusées : chargée à l'ouverture. */
export const useImportRun = (id: string | null) =>
  useQuery({ queryKey: ['integrations', 'import-run', id ?? ''], queryFn: () => importRunsApi.get(id ?? ''), enabled: id !== null })
