import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { CommunicationAttachment, OrderCommunication } from '../types/communication'

/**
 * Charge utile de `StoreOrderCommunicationRequest`.
 *
 * `communicationRuleId` n'y figure pas : une communication manuelle n'en a pas,
 * et le champ est `nullable` côté serveur. L'omettre vaut mieux que d'envoyer
 * `null` explicitement — l'écran ne prétend rien savoir des règles.
 *
 * `scheduledAt` **est** la programmation : il n'existe pas de route `schedule`,
 * et poser une date fait passer le brouillon en `scheduled`.
 */
export interface OrderCommunicationPayload {
  orderId: string
  templateId?: string | null
  channel: string
  communicationType: string
  recipientRole: string
  recipientName?: string | null
  recipientEmail?: string | null
  recipientPhone?: string | null
  subject?: string | null
  body?: string | null
  scheduledAt?: string | null
}

export interface OrderCommunicationFilters {
  page: number
  perPage: number
  channel?: string
  recipientRole?: string
  sort?: string
  direction?: 'asc' | 'desc'
}

/**
 * Communications d'une commande.
 *
 * Il n'existe **pas** de route `send` : le verbe est `queue`. Rien ne part en
 * direct — la communication entre en file, puis le statut passe par `queued`,
 * `sending`, `sent`.
 */
export const orderCommunicationsApi = {
  byOrder: (orderId: string, filters: OrderCommunicationFilters) =>
    api.get<ApiCollection<OrderCommunication>>(`/orders/${orderId}/communications`, {
      query: { ...filters },
    }),

  get: (id: string) =>
    api
      .get<ApiResource<OrderCommunication>>(`/order-communications/${id}`)
      .then((response) => response.data),

  create: (orderId: string, payload: Omit<OrderCommunicationPayload, 'orderId'>) =>
    api
      .post<ApiResource<OrderCommunication>>(`/orders/${orderId}/communications`, payload)
      .then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/order-communications/${id}`),

  queue: (id: string) =>
    api
      .post<ApiResource<OrderCommunication>>(`/order-communications/${id}/queue`, {})
      .then((response) => response.data),

  retry: (id: string) =>
    api
      .post<ApiResource<OrderCommunication>>(`/order-communications/${id}/retry`, {})
      .then((response) => response.data),

  cancel: (id: string) =>
    api
      .post<ApiResource<OrderCommunication>>(`/order-communications/${id}/cancel`, {})
      .then((response) => response.data),

  attachments: (communicationId: string) =>
    api
      .get<ApiResource<CommunicationAttachment[]>>(
        `/order-communications/${communicationId}/attachments`,
      )
      .then((response) => response.data),

  attach: (communicationId: string, documentId: string) =>
    api
      .post<ApiResource<CommunicationAttachment>>(
        `/order-communications/${communicationId}/attachments`,
        { documentId },
      )
      .then((response) => response.data),

  detach: (communicationId: string, attachmentId: string) =>
    api.delete<void>(`/order-communications/${communicationId}/attachments/${attachmentId}`),
}
