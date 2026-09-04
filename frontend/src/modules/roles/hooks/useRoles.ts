import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { rolesApi, type RoleCreatePayload, type RoleUpdatePayload } from '../api/roles.api'
import type { RoleFilters } from '../types/role'

export const roleKeys = {
  all: ['roles'] as const,
  lists: () => [...roleKeys.all, 'list'] as const,
  list: (filters: RoleFilters) => [...roleKeys.lists(), filters] as const,
  detail: (id: string) => [...roleKeys.all, 'detail', id] as const,
  permissions: ['permissions'] as const,
}

export function useRoleList(filters: RoleFilters) {
  return useQuery({
    queryKey: roleKeys.list(filters),
    queryFn: () => rolesApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useRole(id: string | undefined) {
  return useQuery({
    queryKey: roleKeys.detail(id ?? ''),
    queryFn: () => rolesApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

/**
 * Référentiel de permissions.
 *
 * Il est versionné avec le code et ne change pas en cours de session : le
 * garder longtemps en cache évite de le retélécharger à chaque ouverture d'un
 * rôle.
 */
export function usePermissions() {
  return useQuery({
    queryKey: roleKeys.permissions,
    queryFn: () => rolesApi.permissions(),
    staleTime: 30 * 60 * 1000,
  })
}

export function useCreateRole() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: RoleCreatePayload) => rolesApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: roleKeys.lists() })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateRole(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: RoleUpdatePayload) => rolesApi.update(id, payload),
    onSuccess: (role) => {
      queryClient.setQueryData(roleKeys.detail(id), role)
      void queryClient.invalidateQueries({ queryKey: roleKeys.lists() })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteRole() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => rolesApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: roleKeys.lists() })
      toast.success(t('toast.deleted'))
    },
  })
}
