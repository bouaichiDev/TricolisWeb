import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { roleMenuApi, type RoleMenuUpdateItem } from '../api/roleMenu.api'
import { menuKeys } from '@/modules/menu/hooks/useMenu'
import type { MenuItem } from '@/modules/menu/types/menu'

export const roleMenuKeys = {
  all: ['role-menu'] as const,
  detail: (roleId: string) => [...roleMenuKeys.all, roleId] as const,
}

export function useRoleMenu(roleId: string) {
  return useQuery({
    queryKey: roleMenuKeys.detail(roleId),
    queryFn: () => roleMenuApi.get(roleId),
    enabled: roleId !== '',
  })
}

/**
 * Toutes les écritures partagent le même après-coup.
 *
 * Elles renvoient le réglage entier, qu'on pose dans le cache sans recharger.
 * Et elles invalident le menu **effectif** : l'administrateur qui règle un rôle
 * le porte souvent, et il verrait sinon l'ancien menu dans sa propre barre
 * latérale, longuement mise en cache.
 */
function useRoleMenuMutation<TVariables>(
  roleId: string,
  mutationFn: (variables: TVariables) => Promise<MenuItem[]>,
  messageKey: string,
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: (items) => {
      queryClient.setQueryData(roleMenuKeys.detail(roleId), items)
      void queryClient.invalidateQueries({ queryKey: menuKeys.effective() })
      toast.success(t(messageKey))
    },
  })
}

export function useUpdateRoleMenu(roleId: string) {
  return useRoleMenuMutation(
    roleId,
    (items: RoleMenuUpdateItem[]) => roleMenuApi.update(roleId, items),
    'menu.saved',
  )
}

export function useCreateRoleMenuGroup(roleId: string) {
  return useRoleMenuMutation(
    roleId,
    (group: { label: string; icon: string }) => roleMenuApi.createGroup(roleId, group),
    'menu.groupCreated',
  )
}

export function useDeleteRoleMenuGroup(roleId: string) {
  return useRoleMenuMutation(
    roleId,
    (code: string) => roleMenuApi.deleteGroup(roleId, code),
    'menu.groupDeleted',
  )
}
