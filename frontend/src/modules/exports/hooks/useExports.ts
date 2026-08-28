import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { exportsApi } from '../api/exports.api'
import type { ExportConfigurationPayload, ExportJobFilters } from '../types/export'

export const exportKeys = {
  configurations: (customerId: string) => ['export-configurations', customerId] as const,
  jobs: (filters: ExportJobFilters) => ['export-jobs', filters] as const,
}

export function useExportConfigurations(customerId: string) {
  return useQuery({
    queryKey: exportKeys.configurations(customerId),
    queryFn: () => exportsApi.configurations(customerId),
    enabled: customerId !== '',
  })
}

export function useExportJobs(filters: ExportJobFilters) {
  return useQuery({
    queryKey: exportKeys.jobs(filters),
    queryFn: () => exportsApi.jobs(filters),
    placeholderData: (previous) => previous,
  })
}

export function useCreateExportConfiguration(customerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: ExportConfigurationPayload) =>
      exportsApi.createConfiguration(customerId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: exportKeys.configurations(customerId) })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateExportConfiguration(customerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<ExportConfigurationPayload> }) =>
      exportsApi.updateConfiguration(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: exportKeys.configurations(customerId) })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteExportConfiguration(customerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => exportsApi.removeConfiguration(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: exportKeys.configurations(customerId) })
      toast.success(t('toast.deleted'))
    },
  })
}

/**
 * Relancer un envoi.
 *
 * L'envoi repart en file : la réponse dit seulement qu'il a été remis en
 * attente, pas qu'il est arrivé. L'historique se rafraîchit donc, et c'est lui
 * qui dira l'issue.
 */
export function useRetryExportJob() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => exportsApi.retryJob(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['export-jobs'] })
      toast.success(t('exports.jobs.retried'))
    },
  })
}
