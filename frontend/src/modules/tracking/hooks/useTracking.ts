import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { orderPositionsApi, trackingApi } from '../api/tracking.api'
import { trackingDefinitionsApi } from '../api/trackingDefinitions.api'
import type { TrackingEventFilters, TrackingEventPayload } from '../types/trackingEvent'
import type { TrackingDefinitionPayload } from '../types/trackingDefinition'

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

/**
 * Étapes du parcours configurées par l'organisation.
 *
 * Actives seulement, triées par `position` : c'est l'ordre du parcours, pas
 * celui de la création.
 */
export function useTrackingDefinitions(enabled = true) {
  return useQuery({
    queryKey: [...trackingKeys.all, 'definitions'] as const,
    queryFn: () => trackingDefinitionsApi.list({ page: 1, perPage: 100 }),
    enabled,
    staleTime: 5 * 60 * 1000,
  })
}

function useDefinitionMutation<TVariables>(
  mutationFn: (variables: TVariables) => Promise<unknown>,
  message: 'created' | 'updated' | 'deleted',
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      // Toute la racine : le parcours affiche sur les commandes en depend.
      void queryClient.invalidateQueries({ queryKey: trackingKeys.all })
      toast.success(t(`toast.${message}`))
    },
  })
}

export function useCreateTrackingDefinition() {
  return useDefinitionMutation(
    (payload: TrackingDefinitionPayload) => trackingDefinitionsApi.create(payload),
    'created',
  )
}

export function useUpdateTrackingDefinition() {
  return useDefinitionMutation(
    ({ id, ...payload }: Partial<TrackingDefinitionPayload> & { id: string }) =>
      trackingDefinitionsApi.update(id, payload),
    'updated',
  )
}

export function useDeleteTrackingDefinition() {
  return useDefinitionMutation((id: string) => trackingDefinitionsApi.remove(id), 'deleted')
}

/**
 * Positions du véhicule, réinterrogées régulièrement.
 *
 * Trente secondes : un camion parcourt cinq cents mètres dans ce laps de temps,
 * ce qui suffit à suivre une livraison. Plus court multiplierait les appels au
 * fournisseur — qui les facture — sans rien montrer de plus.
 *
 * Le rafraîchissement s'arrête quand l'onglet passe en arrière-plan : personne
 * ne regarde, et l'appel serait perdu.
 */
export function useOrderPositions(orderId: string, enabled: boolean) {
  return useQuery({
    queryKey: [...trackingKeys.byOrder(orderId), 'positions'] as const,
    queryFn: () => orderPositionsApi.get(orderId),
    enabled: enabled && orderId !== '',
    refetchInterval: 30_000,
    refetchIntervalInBackground: false,
  })
}
