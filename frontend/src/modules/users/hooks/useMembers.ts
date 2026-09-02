import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import {
  membersApi,
  type MemberCreatePayload,
  type MemberUpdatePayload,
} from '../api/members.api'
import type { MemberFilters } from '../types/member'

export const memberKeys = {
  all: ['organization-users'] as const,
  lists: () => [...memberKeys.all, 'list'] as const,
  list: (filters: MemberFilters) => [...memberKeys.lists(), filters] as const,
  detail: (id: string) => [...memberKeys.all, 'detail', id] as const,
}

export function useMemberList(filters: MemberFilters) {
  return useQuery({
    queryKey: memberKeys.list(filters),
    queryFn: () => membersApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useMember(id: string | undefined) {
  return useQuery({
    queryKey: memberKeys.detail(id ?? ''),
    queryFn: () => membersApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

export function useCreateMember() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: MemberCreatePayload) => membersApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: memberKeys.lists() })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateMember(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: MemberUpdatePayload) => membersApi.update(id, payload),
    onSuccess: (member) => {
      queryClient.setQueryData(memberKeys.detail(id), member)
      void queryClient.invalidateQueries({ queryKey: memberKeys.lists() })
      toast.success(t('toast.updated'))
    },
  })
}

/** Désactivation, pas suppression : c'est ce que fait réellement l'API. */
export function useDisableMember() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => membersApi.disable(id),
    onSuccess: (_data, id) => {
      void queryClient.invalidateQueries({ queryKey: memberKeys.lists() })
      void queryClient.invalidateQueries({ queryKey: memberKeys.detail(id) })
      toast.success(t('users.disabled'))
    },
  })
}

/**
 * Envoie au membre un lien de réinitialisation.
 *
 * Le succès nomme l'adresse servie : sans elle, l'administrateur ne saurait pas
 * où le lien est parti, et une adresse périmée passerait pour un envoi réussi.
 */
export function useSendPasswordResetLink() {
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => membersApi.sendPasswordResetLink(id),
    onSuccess: (result) => toast.success(t('users.password.linkSent', { email: result.email })),
  })
}

/** Pose un mot de passe pour le membre, pour les comptes sans boîte relevée. */
export function useSetMemberPassword() {
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, password }: { id: string; password: string }) =>
      membersApi.setPassword(id, password),
    onSuccess: () => toast.success(t('users.password.set')),
  })
}
