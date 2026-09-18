import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'

import type { OrderServiceContactPayload } from '../types/orderPayload'
import type { OrderServiceContact } from '../types/orderDetail'

const base = (orderId: string, serviceId: string) =>
  `/orders/${orderId}/services/${serviceId}/contacts`

/**
 * Contacts d'un service d'une commande existante — `OrderServiceContactController`.
 *
 * Même charge utile qu'à la création complète : un contact enregistré désigné
 * par `contactId`, ou un contact ponctuel décrit champ par champ. Le serveur
 * recopie les coordonnées et ne garde qu'un contact principal par rôle.
 */
export const serviceContactsApi = {
  create: (orderId: string, serviceId: string, payload: OrderServiceContactPayload) =>
    api
      .post<ApiResource<OrderServiceContact>>(base(orderId, serviceId), payload)
      .then((response) => response.data),

  update: (
    orderId: string,
    serviceId: string,
    contactId: string,
    payload: OrderServiceContactPayload,
  ) =>
    api
      .patch<ApiResource<OrderServiceContact>>(`${base(orderId, serviceId)}/${contactId}`, payload)
      .then((response) => response.data),

  remove: (orderId: string, serviceId: string, contactId: string) =>
    api.delete<void>(`${base(orderId, serviceId)}/${contactId}`),
}
