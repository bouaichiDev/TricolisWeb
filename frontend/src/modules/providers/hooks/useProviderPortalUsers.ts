import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { ApiError } from '@/shared/api/errors'

import { providerPortalUsersApi } from '../api/providerPortalUsers.api'
import type { ProviderPortalInvitePayload, ProviderPortalUserAction } from '../types/providerPortalUser'

/**
 * Un refus du serveur dit quoi faire (« renvoyez-la plutôt ») : on montre la
 * première raison de champ, sinon le message, jamais un silence.
 */
function useRefusal() {
  const { t } = useTranslation()

  return (error: unknown) => {
    const reason = error instanceof ApiError ? (Object.values(error.errors)[0]?.[0] ?? error.message) : t('errors.unexpected')
    toast.error(reason)
  }
}

const keys = {
  members: (providerId: string) => ['providers', providerId, 'portal-users'] as const,
  roles: (providerId: string) => ['providers', providerId, 'portal-roles'] as const,
}

export function useProviderPortalUsers(providerId: string, enabled: boolean) {
  return useQuery({
    queryKey: keys.members(providerId),
    queryFn: () => providerPortalUsersApi.list(providerId),
    enabled,
  })
}

export function useProviderPortalRoles(providerId: string, enabled: boolean) {
  return useQuery({
    queryKey: keys.roles(providerId),
    queryFn: () => providerPortalUsersApi.roles(providerId),
    enabled,
    staleTime: 5 * 60 * 1000,
  })
}

export function useInviteProviderPortalUser(providerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: ProviderPortalInvitePayload) => providerPortalUsersApi.invite(providerId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: keys.members(providerId) })
      toast.success(t('providers.portal.invited'))
    },
  })
}

export function useChangeProviderPortalRole(providerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()
  const refuse = useRefusal()

  return useMutation({
    mutationFn: ({ memberId, roleId }: { memberId: string; roleId: string }) =>
      providerPortalUsersApi.changeRole(providerId, memberId, roleId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: keys.members(providerId) })
      toast.success(t('toast.updated'))
    },
    onError: refuse,
  })
}

export function useProviderPortalUserAction(providerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()
  const refuse = useRefusal()

  return useMutation({
    mutationFn: ({ memberId, action }: { memberId: string; action: ProviderPortalUserAction }) =>
      providerPortalUsersApi.act(providerId, memberId, action),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: keys.members(providerId) })
      toast.success(t('toast.updated'))
    },
    onError: refuse,
  })
}
