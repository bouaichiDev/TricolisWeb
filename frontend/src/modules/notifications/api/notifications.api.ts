import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'

/**
 * Une notification, telle que la cloche l'affiche.
 *
 * `title` est l'objet du message, ou le nom du destinataire quand il n'y en a
 * pas — un SMS n'a pas d'objet, et une ligne sans titre serait illisible.
 *
 * `route` est `null` quand la communication n'est rattachée à aucune commande :
 * la carte n'ouvre alors rien, plutôt que de mener à une page introuvable.
 */
export interface AppNotification {
  id: string
  title: string | null
  recipient: string | null
  channel: string | null
  status: string | null
  isRead: boolean
  date: string | null
  route: string | null
}

/**
 * Les deux moitiés que le domaine distingue déjà.
 *
 * `internal` — ce qui m'est adressé, reconnaissable à mon adresse, avec un état
 * de lecture qui n'appartient qu'à moi. `external` — les envois de
 * l'organisation à ses clients **qui ont échoué**, sans état de lecture : ils ne
 * sont adressés à personne ici, et c'est l'organisation entière qui les reprend.
 *
 * `unread` ne compte que les internes. Un compteur qu'aucun geste ne fait
 * baisser cesse d'être lu au bout d'une journée.
 */
export interface NotificationFeed {
  unread: number
  internal: AppNotification[]
  external: AppNotification[]
}

export const notificationsApi = {
  feed: () =>
    api.get<ApiResource<NotificationFeed>>('/notifications').then((response) => response.data),

  markAsRead: (id: string) =>
    api.post<ApiResource<{ unread: number }>>(`/notifications/${id}/read`),

  markAllAsRead: () => api.post<ApiResource<{ unread: number }>>('/notifications/read-all'),
}
