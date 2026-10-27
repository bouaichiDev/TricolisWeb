import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { usePermissions } from '@/app/auth/useAuth'
import type { PortalUser } from '@/shared/api/types'

import { usersApi, type UserFilters } from '../api/usersApi'

export const usersKeys = {
  all: ['users'] as const,
  list: (filters: UserFilters) => ['users', 'list', filters] as const,
  detail: (id: string) => ['users', 'detail', id] as const,
  roles: ['roles'] as const,
}

export function useUsers(filters: UserFilters) {
  return useQuery({
    queryKey: usersKeys.list(filters),
    queryFn: () => usersApi.list(filters),
    placeholderData: keepPreviousData,
  })
}

export function useUser(id: string) {
  return useQuery({ queryKey: usersKeys.detail(id), queryFn: () => usersApi.get(id) })
}

/** Les rôles ne se chargent que si le membre peut les voir. */
export function useRoles() {
  const { can } = usePermissions()

  return useQuery({
    queryKey: usersKeys.roles,
    queryFn: usersApi.roles,
    enabled: can('customer.roles.view'),
    staleTime: 5 * 60 * 1000,
  })
}

/**
 * Une mutation sur un membre : la réponse remplace la fiche en cache, et les
 * listes sont rechargées.
 */
export function useUserMutation<TVariables>(mutationFn: (variables: TVariables) => Promise<PortalUser>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSuccess: (user) => {
      queryClient.setQueryData(usersKeys.detail(user.id), user)
      void queryClient.invalidateQueries({ queryKey: [...usersKeys.all, 'list'] })
    },
  })
}
