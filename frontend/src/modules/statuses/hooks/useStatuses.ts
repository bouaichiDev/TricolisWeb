import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { ApiError } from '@/shared/api/errors'

import { statusesApi } from '../api/statuses.api'
import type { StatusFilters, StatusPayload, StatusTransitionInput } from '../types/status'

export const statusKeys = {
  all: ['statuses'] as const,
  lists: () => [...statusKeys.all, 'list'] as const,
  list: (filters: StatusFilters) => [...statusKeys.lists(), filters] as const,
  sources: () => [...statusKeys.all, 'sources'] as const,
  transitions: (id: string) => [...statusKeys.all, 'transitions', id] as const,
}

export function useStatusList(filters: StatusFilters) {
  return useQuery({
    queryKey: statusKeys.list(filters),
    queryFn: () => statusesApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

/** Cache long : la liste des entités ne change qu'avec le code du serveur. */
export function useStatusSources() {
  return useQuery({
    queryKey: statusKeys.sources(),
    queryFn: () => statusesApi.sources(),
    staleTime: 60 * 60 * 1000,
  })
}

export function useCreateStatus() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: StatusPayload) => statusesApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: statusKeys.lists() })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateStatus() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, ...payload }: Partial<StatusPayload> & { id: string }) =>
      statusesApi.update(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: statusKeys.lists() })
      toast.success(t('toast.updated'))
    },
  })
}

/**
 * Suppression d'un statut.
 *
 * Le serveur refuse d'ôter un statut que des enregistrements portent encore, et
 * son message dit combien : il est remonté tel quel. Sans cela, le clic
 * paraîtrait sans effet.
 */
export function useDeleteStatus() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => statusesApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: statusKeys.lists() })
      toast.success(t('toast.deleted'))
    },
    onError: (error) => {
      const refusal =
        error instanceof ApiError
          ? (Object.values(error.errors)[0]?.[0] ?? error.message)
          : t('errors.unexpected')

      toast.error(refusal)
    },
  })
}

/** Transitions au départ d'un statut : le cycle de vie, tel qu'il est enregistré. */
export function useStatusTransitions(statusId: string, enabled = true) {
  return useQuery({
    queryKey: statusKeys.transitions(statusId),
    queryFn: () => statusesApi.transitions(statusId),
    enabled: enabled && statusId !== '',
  })
}

/**
 * Remplace le jeu de transitions d'un statut.
 *
 * La fiche des commandes en dépend — `allowedTransitions` en vient — d'où
 * l'invalidation des commandes en plus du référentiel.
 */
export function useSyncStatusTransitions(statusId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (transitions: StatusTransitionInput[]) =>
      statusesApi.syncTransitions(statusId, transitions),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: statusKeys.transitions(statusId) })
      void queryClient.invalidateQueries({ queryKey: ['orders'] })
      toast.success(t('toast.updated'))
    },
  })
}
