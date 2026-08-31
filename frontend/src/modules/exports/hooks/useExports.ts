import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { ApiError } from '@/shared/api/errors'

import { customerExportConfigurationsApi } from '../api/customer-export-configurations.api'
import { exportJobsApi } from '../api/export-jobs.api'
import type {
  ExportConfigurationFilters,
  ExportConfigurationPayload,
  ExportJobFilters,
  ExportJobPayload,
} from '../types/export'

/**
 * Clés de cache des exports.
 *
 * **Un seul jeu**, partagé par la Facturation et par les Intégrations : le §77
 * interdit deux implémentations concurrentes d'`ExportJob`. Les écrans
 * `/billing/*` et `/integrations/*` lisent donc le même cache, et une relance
 * déclenchée d'un côté se voit de l'autre.
 */
export const exportKeys = {
  all: ['exports'] as const,

  configurations: () => [...exportKeys.all, 'configurations'] as const,
  configurationList: (filters: ExportConfigurationFilters) =>
    [...exportKeys.configurations(), 'list', filters] as const,
  configurationsOfCustomer: (customerId: string) =>
    [...exportKeys.configurations(), 'customer', customerId] as const,
  configuration: (id: string) => [...exportKeys.configurations(), 'detail', id] as const,

  jobs: (filters: ExportJobFilters) => [...exportKeys.all, 'jobs', 'list', filters] as const,
  jobsRoot: () => [...exportKeys.all, 'jobs'] as const,
  job: (id: string) => [...exportKeys.all, 'jobs', 'detail', id] as const,
}

export function useExportConfigurationList(filters: ExportConfigurationFilters) {
  return useQuery({
    queryKey: exportKeys.configurationList(filters),
    queryFn: () => customerExportConfigurationsApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useExportConfigurations(customerId: string) {
  return useQuery({
    queryKey: exportKeys.configurationsOfCustomer(customerId),
    queryFn: () => customerExportConfigurationsApi.byCustomer(customerId),
    enabled: customerId !== '',
  })
}

export function useExportConfiguration(id: string | undefined) {
  return useQuery({
    queryKey: exportKeys.configuration(id ?? ''),
    queryFn: () => customerExportConfigurationsApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

export function useExportJobs(filters: ExportJobFilters) {
  return useQuery({
    queryKey: exportKeys.jobs(filters),
    queryFn: () => exportJobsApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useExportJob(id: string | undefined) {
  return useQuery({
    queryKey: exportKeys.job(id ?? ''),
    queryFn: () => exportJobsApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

function useInvalidateConfigurations() {
  const queryClient = useQueryClient()

  return () => void queryClient.invalidateQueries({ queryKey: exportKeys.configurations() })
}

/**
 * Crée une destination.
 *
 * Avec un client connu, la route imbriquée refuse d'emblée un client d'une
 * autre organisation ; sinon le client vient du formulaire et la route plate
 * prend le relais.
 */
export function useCreateExportConfiguration(customerId?: string) {
  const invalidate = useInvalidateConfigurations()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: ExportConfigurationPayload) =>
      customerId === undefined || customerId === ''
        ? customerExportConfigurationsApi.create(payload)
        : customerExportConfigurationsApi.createForCustomer(customerId, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateExportConfiguration() {
  const invalidate = useInvalidateConfigurations()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<ExportConfigurationPayload> }) =>
      customerExportConfigurationsApi.update(id, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.updated'))
    },
  })
}

/**
 * Supprime une destination.
 *
 * Les envois passés ne sont **pas** effacés en cascade : le serveur refuse la
 * suppression tant qu'ils s'y rattachent, et son message dit combien (§71). Il
 * est affiché tel quel — « suppression impossible » n'aiderait personne.
 */
export function useDeleteExportConfiguration() {
  const invalidate = useInvalidateConfigurations()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => customerExportConfigurationsApi.remove(id),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.deleted'))
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : t('errors.unexpected'))
    },
  })
}

/**
 * Déclenche un envoi.
 *
 * Le fichier est produit et transmis par `ProcessExportJob`, en file : la
 * réponse dit seulement que l'envoi est enregistré, pas qu'il est arrivé.
 * L'historique dira l'issue.
 */
export function useCreateExportJob() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: ExportJobPayload) => exportJobsApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: exportKeys.jobsRoot() })
      toast.success(t('exports.jobs.created'))
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : t('errors.unexpected'))
    },
  })
}

/**
 * Relance un envoi.
 *
 * L'envoi repart en file : la réponse dit seulement qu'il a été remis en
 * attente, pas qu'il est arrivé. L'historique se rafraîchit donc, et c'est lui
 * qui dira l'issue.
 *
 * Un envoi déjà transmis est refusé en 409 : le renvoyer donnerait au client
 * deux fois la même facture. Le message du serveur est affiché tel quel.
 */
export function useRetryExportJob() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => exportJobsApi.retry(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: exportKeys.jobsRoot() })
      toast.success(t('exports.jobs.retried'))
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : t('errors.unexpected'))
    },
  })
}
