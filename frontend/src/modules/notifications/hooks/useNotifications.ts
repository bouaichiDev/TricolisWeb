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
 * Trois façons de se rafraîchir, et il en faut trois : une cloche qui ne bouge
 * qu'au rechargement de la page ne sert à rien, et les notifications arrivent
 * pendant qu'on travaille — une communication échoue, une règle en déclenche
 * une.
 *
 * | Quand | Pourquoi |
 * | --- | --- |
 * | toutes les **30 secondes** | pour que la pastille bouge sans qu'on ait rien fait |
 * | au **retour sur l'onglet** | c'est le moment où l'on regarde, et où un chiffre périmé se remarque |
 * | à l'**ouverture du panneau** | c'est le moment où l'on lit, et le seul où l'attente coûte |
 *
 * **`staleTime: 0` est ce qui fait tenir les deux derniers.** Sans lui, la
 * requête hérite des trente secondes du client, et React Query considère alors
 * la réponse encore fraîche : le retour sur l'onglet ne redemande rien, et
 * l'ouverture du panneau non plus. C'était le défaut — la cloche semblait
 * n'obéir qu'au rechargement de la page. Un flux de notifications n'est
 * **jamais** frais : c'est la seule donnée de l'application dont l'intérêt est
 * précisément qu'elle a pu changer depuis la dernière seconde.
 *
 * Le sondage s'arrête quand l'onglet passe en arrière-plan
 * (`refetchIntervalInBackground` reste faux) : personne ne regarde, et le
 * retour sur l'onglet rattrape.
 */
export function useNotifications() {
  const { organizationId } = useAuth()

  return useQuery({
    queryKey: notificationKeys.feed(organizationId),
    queryFn: notificationsApi.feed,
    refetchInterval: 30 * 1000,
    refetchOnWindowFocus: true,
    staleTime: 0,
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
