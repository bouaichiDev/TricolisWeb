import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { trackingApi } from '../api/tracking.api'
import type { TrackingEventFilters, TrackingEventPayload } from '../types/trackingEvent'

export const trackingKeys = {
  all: ['tracking'] as const,
  byOrder: (orderId: string) => [...trackingKeys.all, 'order', orderId] as const,
  list: (orderId: string, filters: TrackingEventFilters) =>
    [...trackingKeys.byOrder(orderId), filters] as const,
}

/**
 * Événements de suivi d'une commande.
 *
 * `enabled` porte l'exigence du §51 : l'onglet Tracking ne se charge qu'une
 * fois ouvert. Interroger cette route depuis la liste `/orders` ferait une
 * requête par ligne pour une donnée que personne ne regarde.
 */
export function useOrderTracking(
  orderId: string,
  filters: TrackingEventFilters,
  enabled = true,
) {
  return useQuery({
    queryKey: trackingKeys.list(orderId, filters),
    queryFn: () => trackingApi.byOrder(orderId, filters),
    enabled: enabled && orderId !== '',
    placeholderData: (previous) => previous,
  })
}

/**
 * Ajoute un événement.
 *
 * Le §9 ne l'autorise qu'à trois conditions, toutes vérifiées : la route
 * `POST /tracking-events` existe, la permission `tracking_events.create` est
 * au référentiel, et le backend prévoit l'usage interne.
 */
export function useCreateTrackingEvent(orderId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: TrackingEventPayload) => trackingApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: trackingKeys.byOrder(orderId) })
      toast.success(t('tracking.created'))
    },
  })
}
