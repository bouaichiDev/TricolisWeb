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

/**
 * Statuts du référentiel.
 *
 * `enabled` permet de ne rien demander tant que le panneau qui s'en sert est
 * fermé : la fiche d'une commande n'a pas à charger un référentiel que personne
 * n'a ouvert.
 */
export function useStatusList(filters: StatusFilters, enabled = true) {
  return useQuery({
    queryKey: statusKeys.list(filters),
    queryFn: () => statusesApi.list(filters),
    enabled,
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

/**
 * Statuts d'une entité, prêts pour un formulaire ou un filtre.
 *
 * Le référentiel remplace les listes codées dans chaque module : une valeur
 * ajoutée à `statuses` apparaît partout sans qu'on touche au frontend.
 *
 * **Seuls les statuts actifs sont proposés.** Une donnée ancienne peut porter
 * un code désactivé depuis ; `current` le réinjecte pour qu'il reste visible en
 * modification. Le retirer de la liste ferait perdre l'information sans que
 * personne l'ait demandé — et le serveur refuse de toute façon d'y revenir.
 */
export function useStatusOptions(source: string, current?: string | null) {
  const query = useQuery({
    queryKey: statusKeys.list({ page: 1, perPage: 100, source, active: true }),
    queryFn: () => statusesApi.list({ page: 1, perPage: 100, source, active: true }),
    // Sans source, il n'y a rien a demander : `StatusBadge` s'en sert pour les
    // champs `status` restes en chaine libre, et interroger le referentiel
    // pour chacun d'eux le ferait pour rien.
    enabled: source !== '',
    // Un referentiel de plateforme ne bouge qu'a l'initiative d'un
    // administrateur : le rappeler a chaque ouverture d'un formulaire serait
    // du trafic pour rien.
    staleTime: 10 * 60 * 1000,
  })

  const rows = query.data?.data ?? []
  const options = rows.map((status) => ({
    value: status.code,
    label: status.label,
    hint: status.code,
  }))

  const known = options.some((option) => option.value === current)

  if (current != null && current !== '' && !known && !query.isPending) {
    options.push({ value: current, label: current, hint: current })
  }

  return { isLoading: query.isPending, options, statuses: rows }
}

/** Libellé d'un code, ou le code lui-même tant que le référentiel n'a pas répondu. */
export function useStatusLabel(source: string, code: string | null | undefined) {
  const { statuses } = useStatusOptions(source, code)

  if (!code) return null

  return statuses.find((status) => status.code === code)?.label ?? code
}
