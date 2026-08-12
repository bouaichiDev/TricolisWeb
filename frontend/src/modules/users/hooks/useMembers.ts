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
