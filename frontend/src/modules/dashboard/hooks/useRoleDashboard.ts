import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { roleDashboardApi } from '../api/role-dashboard.api'
import { dashboardKeys } from './useDashboard'
import type { RoleDashboardWidget, RoleDashboardWidgetSelection } from '../types/dashboard'

export const roleDashboardKeys = {
  all: ['role-dashboard'] as const,
  detail: (roleId: string) => [...roleDashboardKeys.all, roleId] as const,
}

export function useRoleDashboard(roleId: string) {
  return useQuery({
    queryKey: roleDashboardKeys.detail(roleId),
    queryFn: () => roleDashboardApi.get(roleId),
    enabled: roleId !== '',
  })
}

/**
 * Les deux écritures partagent le même après-coup.
 *
 * Elles renvoient le réglage entier, qu'on pose dans le cache sans recharger.
 * Et elles invalident le tableau de bord **courant** : celui qui règle un rôle
 * le porte souvent lui-même, et il verrait sinon son propre écran inchangé —
 * en concluant que l'enregistrement n'a pas pris.
 *
 * L'invalidation est large, sans l'identifiant d'organisation : le rôle réglé
 * n'est pas forcément dans celle qui est active, et cibler la mauvaise clé
 * n'aurait rien rafraîchi du tout.
 */
function useRoleDashboardMutation<TVariables>(
  roleId: string,
  mutationFn: (variables: TVariables) => Promise<RoleDashboardWidget[]>,
  messageKey: string,
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: (widgets) => {
      queryClient.setQueryData(roleDashboardKeys.detail(roleId), widgets)
      void queryClient.invalidateQueries({ queryKey: dashboardKeys.all })
      toast.success(t(messageKey))
    },
  })
}

export function useUpdateRoleDashboard(roleId: string) {
  return useRoleDashboardMutation(
    roleId,
    (widgets: RoleDashboardWidgetSelection[]) => roleDashboardApi.update(roleId, widgets),
    'dashboardSettings.saved',
  )
}

export function useResetRoleDashboard(roleId: string) {
  return useRoleDashboardMutation(
    roleId,
    () => roleDashboardApi.reset(roleId),
    'dashboardSettings.reset',
  )
}
