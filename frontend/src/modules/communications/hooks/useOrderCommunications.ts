import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import {
  orderCommunicationsApi,
  type OrderCommunicationFilters,
  type OrderCommunicationPayload,
} from '../api/order-communications.api'

export const orderCommunicationKeys = {
  all: ['order-communications'] as const,
  byOrder: (orderId: string) => [...orderCommunicationKeys.all, 'order', orderId] as const,
  list: (orderId: string, filters: OrderCommunicationFilters) =>
    [...orderCommunicationKeys.byOrder(orderId), filters] as const,
  detail: (id: string) => [...orderCommunicationKeys.all, 'detail', id] as const,
  attachments: (id: string) => [...orderCommunicationKeys.all, 'attachments', id] as const,
  history: (filters: OrderCommunicationFilters) =>
    [...orderCommunicationKeys.all, 'history', filters] as const,
}

/**
 * Historique de toute l'organisation.
 *
 * Filtres et pagination restent au serveur : le §140 interdit de descendre
 * l'historique entier pour trier dans le navigateur.
 */
export function useCommunicationHistory(filters: OrderCommunicationFilters, enabled = true) {
  return useQuery({
    queryKey: orderCommunicationKeys.history(filters),
    queryFn: () => orderCommunicationsApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useOrderCommunications(
  orderId: string,
  filters: OrderCommunicationFilters,
  enabled = true,
) {
  return useQuery({
    queryKey: orderCommunicationKeys.list(orderId, filters),
    queryFn: () => orderCommunicationsApi.byOrder(orderId, filters),
    enabled: enabled && orderId !== '',
    placeholderData: (previous) => previous,
  })
}

/** Le détail porte le corps complet et les pièces jointes ; la liste non. */
export function useOrderCommunication(id: string | null) {
  return useQuery({
    queryKey: orderCommunicationKeys.detail(id ?? ''),
    queryFn: () => orderCommunicationsApi.get(id ?? ''),
    enabled: id !== null && id !== '',
  })
}

/**
 * Écritures et transitions.
 *
 * Chacune invalide la liste **et** le détail : mettre en file depuis le tiroir
 * change le statut affiché des deux côtés, et n'en rafraîchir qu'un laisserait
 * l'autre montrer un état révolu.
 */
function useCommunicationMutation<TVariables>(
  mutationFn: (variables: TVariables) => Promise<unknown>,
  message: string,
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: orderCommunicationKeys.all })
      toast.success(t(message))
    },
  })
}

export function useCreateOrderCommunication(orderId: string) {
  return useCommunicationMutation(
    (payload: Omit<OrderCommunicationPayload, 'orderId'>) =>
      orderCommunicationsApi.create(orderId, payload),
    'communications.created',
  )
}

export function useQueueOrderCommunication() {
  return useCommunicationMutation(
    (id: string) => orderCommunicationsApi.queue(id),
    'communications.queued',
  )
}

export function useRetryOrderCommunication() {
  return useCommunicationMutation(
    (id: string) => orderCommunicationsApi.retry(id),
    'communications.retried',
  )
}

export function useCancelOrderCommunication() {
  return useCommunicationMutation(
    (id: string) => orderCommunicationsApi.cancel(id),
    'communications.cancelled',
  )
}

export function useDeleteOrderCommunication() {
  return useCommunicationMutation(
    (id: string) => orderCommunicationsApi.remove(id),
    'toast.deleted',
  )
}

export function useCommunicationAttachments(communicationId: string | null) {
  return useQuery({
    queryKey: orderCommunicationKeys.attachments(communicationId ?? ''),
    queryFn: () => orderCommunicationsApi.attachments(communicationId ?? ''),
    enabled: communicationId !== null && communicationId !== '',
  })
}

export function useAttachDocument(communicationId: string) {
  return useCommunicationMutation(
    (documentId: string) => orderCommunicationsApi.attach(communicationId, documentId),
    'communications.attached',
  )
}

export function useDetachDocument(communicationId: string) {
  return useCommunicationMutation(
    (attachmentId: string) => orderCommunicationsApi.detach(communicationId, attachmentId),
    'communications.detached',
  )
}
