import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { integrationsApi, type ExportJobFilters } from '../api/integrationsApi'

export const integrationKeys = {
  all: ['integrations'] as const,
  overview: ['integrations', 'overview'] as const,
  exportJobs: (filters: ExportJobFilters) => ['integrations', 'export-jobs', filters] as const,
  importResult: (token: string) => ['integrations', 'import-result', token] as const,
}

export const useIntegrationsOverview = () => useQuery({ queryKey: integrationKeys.overview, queryFn: integrationsApi.overview })

export const useExportJobs = (filters: ExportJobFilters) =>
  useQuery({ queryKey: integrationKeys.exportJobs(filters), queryFn: () => integrationsApi.exportJobs(filters), placeholderData: keepPreviousData })

/**
 * Le résumé d'un import parti en file, relu tant qu'il n'est pas terminé.
 */
export const useImportResult = (token: string | null) =>
  useQuery({
    queryKey: integrationKeys.importResult(token ?? ''),
    queryFn: () => integrationsApi.result(token ?? ''),
    enabled: token !== null,
    refetchInterval: (query) => (['queued', 'running'].includes(query.state.data?.status ?? 'queued') ? 3000 : false),
  })

/** Une écriture (relance, import) périme la vue d'ensemble et les envois. */
export function useIntegrationMutation<TVariables, TResult>(mutationFn: (variables: TVariables) => Promise<TResult>) {
  const queryClient = useQueryClient()

  return useMutation({ mutationFn, onSuccess: () => queryClient.invalidateQueries({ queryKey: integrationKeys.all }) })
}
