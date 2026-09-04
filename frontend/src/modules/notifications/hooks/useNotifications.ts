import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { notificationsApi } from '../api/notifications.api'
import { useAuth } from '@/shared/hooks/useAuth'

export const notificationKeys = {
  all: ['notifications'] as const,
  /**
   * L'organisation fait partie de la clé.
   *
   * Le même compte peut travailler dans deux organisations, et les
   * notifications d'une n'ont rien à faire dans l'autre. Une clé commune aurait
   * gardé les premières à l'écran le temps d'un rafraîchissement, sous le nom du
   * mauvais organisme.
   */
  feed: (organizationId: string | null) => [...notificationKeys.all, organizationId] as const,
}

/**
 * La cloche du bandeau.
 *
 * Elle se rafraîchit toute seule, à la minute. Les notifications arrivent
 * pendant qu'on travaille — une communication échoue, une règle en déclenche
 * une — et une cloche qui ne bouge qu'au rechargement de la page ne sert à
 * rien. La minute est un compromis assumé : plus court ferait une requête pour
 * rien la plupart du temps, plus long ferait rater ce qui vient de se produire.
 *
 * `refetchOnWindowFocus` s'y ajoute : revenir sur l'onglet est le moment où
 * l'on regarde, et c'est là qu'un chiffre périmé se remarque.
 */
export function useNotifications() {
  const { organizationId } = useAuth()

  return useQuery({
    queryKey: notificationKeys.feed(organizationId),
    queryFn: notificationsApi.feed,
    refetchInterval: 60 * 1000,
    refetchOnWindowFocus: true,
  })
}

/**
 * Marquer comme lu, une par une ou toutes.
 *
 * Les deux invalident le flux entier plutôt que d'y retoucher : la réponse ne
 * rend que le nouveau compte, et reconstruire la liste à la main ferait un
 * second endroit où la règle « lu » s'écrit.
 */
function useReadMutation<TVariables>(mutationFn: (variables: TVariables) => Promise<unknown>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: notificationKeys.all })
    },
  })
}

export function useMarkNotificationRead() {
  return useReadMutation((id: string) => notificationsApi.markAsRead(id))
}

export function useMarkAllNotificationsRead() {
  return useReadMutation(() => notificationsApi.markAllAsRead())
}
