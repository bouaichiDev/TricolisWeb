import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { trackingApi } from '../api/trackingApi'
import type { PublicLinkState } from '../types'
import { trackingKeys } from './useOrderTracking'

export function usePublicTrackingLink(orderId: string, enabled: boolean) {
  return useQuery({ queryKey: trackingKeys.publicLink(orderId), queryFn: () => trackingApi.publicLink(orderId), enabled })
}

/**
 * Générer ou révoquer : la réponse devient l'état affiché. À la génération,
 * elle porte l'URL — la seule fois où elle existe — et on la garde telle
 * quelle plutôt que de relire un état qui ne l'a plus.
 */
export function usePublicLinkMutation<TVariables>(orderId: string, mutationFn: (variables: TVariables) => Promise<PublicLinkState>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSuccess: (state) => queryClient.setQueryData(trackingKeys.publicLink(orderId), state),
  })
}
