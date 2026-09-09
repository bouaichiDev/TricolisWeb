import { useQuery } from '@tanstack/react-query'

import { dashboardApi } from '../api/dashboard.api'
import type { DashboardPeriod } from '../types/dashboard'
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
   *
   * La période y figure pour la même raison, à l'échelle d'un écran : deux
   * périodes rendent deux tableaux de bord différents, et les servir sous la
   * même clé aurait affiché les chiffres d'août sous un filtre réglé sur
   * septembre le temps d'un aller-retour.
   */
  current: (organizationId: string | null, period: DashboardPeriod | null) =>
    [...dashboardKeys.all, 'current', organizationId, period?.from ?? null, period?.to ?? null] as const,
}

/**
 * Le tableau de bord de l'utilisateur connecté.
 *
 * Aucun filtrage ici : ce qui arrive est déjà ce qu'il a le droit de voir. Un
 * `PermissionGuard` posé par-dessus serait au mieux redondant, au pire
 * trompeur — il laisserait croire que la protection est là.
 *
 * La période, elle, ne retire aucune carte : elle déplace la fenêtre de celles
 * qui l'ont déclarée. Les autres répondent la même chose quoi qu'on choisisse,
 * et l'écran le dit en les rangeant à part.
 */
export function useDashboard(period: DashboardPeriod | null) {
  const { membership } = useAuth()
  const organizationId = membership?.id ?? null

  return useQuery({
    queryKey: dashboardKeys.current(organizationId, period),
    queryFn: () => dashboardApi.current(period),
    enabled: organizationId !== null,
    // La réponse précédente reste à l'écran pendant qu'on change de période :
    // sans elle, la grille disparaîtrait et reviendrait à chaque frappe dans le
    // sélecteur de dates, ce qui se lit comme une erreur.
    placeholderData: (previous) => previous,
  })
}
