import { useQuery } from '@tanstack/react-query'

import { dashboardApi } from '../api/dashboard.api'
import { useAuth } from '@/shared/hooks/useAuth'

export const dashboardKeys = {
  all: ['dashboard'] as const,
  /**
   * L'organisation fait partie de la clé, et il le faut.
   *
   * Le même compte peut travailler dans deux organisations, avec des rôles
   * différents dans chacune. Une clé commune aurait servi les chiffres de la
   * première dans la seconde le temps d'un rafraîchissement — des totaux justes
   * attribués au mauvais organisme, ce que personne ne repère.
   */
  current: (organizationId: string | null) => [...dashboardKeys.all, 'current', organizationId] as const,
}

/**
 * Le tableau de bord de l'utilisateur connecté.
 *
 * Aucun filtrage ici : ce qui arrive est déjà ce qu'il a le droit de voir. Un
 * `PermissionGuard` posé par-dessus serait au mieux redondant, au pire
 * trompeur — il laisserait croire que la protection est là.
 */
export function useDashboard() {
  const { membership } = useAuth()
  const organizationId = membership?.id ?? null

  return useQuery({
    queryKey: dashboardKeys.current(organizationId),
    queryFn: dashboardApi.current,
    enabled: organizationId !== null,
  })
}
