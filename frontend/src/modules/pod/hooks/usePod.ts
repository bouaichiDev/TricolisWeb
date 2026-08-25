import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { podApi } from '../api/pod.api'
import type { ProofOfDeliveryFilters, ProofOfDeliveryPayload } from '../types/proofOfDelivery'

export const podKeys = {
  all: ['pods'] as const,
  byOrder: (orderId: string) => [...podKeys.all, 'order', orderId] as const,
  list: (orderId: string, filters: ProofOfDeliveryFilters) =>
    [...podKeys.byOrder(orderId), filters] as const,
  detail: (id: string) => [...podKeys.all, 'detail', id] as const,
}

/** Preuves d'une commande. Chargées à l'ouverture de l'onglet, pas avant. */
export function useOrderPods(
  orderId: string,
  filters: ProofOfDeliveryFilters,
  enabled = true,
) {
  return useQuery({
    queryKey: podKeys.list(orderId, filters),
    queryFn: () => podApi.byOrder(orderId, filters),
    enabled: enabled && orderId !== '',
    placeholderData: (previous) => previous,
  })
}

/**
 * Détail d'une preuve.
 *
 * Une requête de plus, et c'est voulu : la liste ne renvoie que les
 * identifiants de la signature et de la photo. Charger les deux documents pour
 * chaque ligne d'une liste que personne n'ouvrira coûterait plus que ce détail.
 */
export function usePod(id: string | null) {
  return useQuery({
    queryKey: podKeys.detail(id ?? ''),
    queryFn: () => podApi.get(id ?? ''),
    enabled: id !== null && id !== '',
  })
}

export function useCreatePod(orderId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: ProofOfDeliveryPayload) => podApi.create(orderId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: podKeys.byOrder(orderId) })
      toast.success(t('pod.created'))
    },
  })
}
