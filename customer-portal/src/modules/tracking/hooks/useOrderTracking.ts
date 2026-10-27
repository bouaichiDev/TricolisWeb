import { useQuery } from '@tanstack/react-query'

import { trackingApi } from '../api/trackingApi'

export const trackingKeys = {
  order: (id: string) => ['tracking', 'order', id] as const,
  publicLink: (id: string) => ['tracking', 'public-link', id] as const,
}

/**
 * Suivi d'une commande, rafraîchi par interrogation contrôlée (§19).
 *
 * L'intervalle vient du backend (`refreshSeconds`) : il est nul quand la
 * commande n'évolue plus, et l'interrogation s'arrête. Pas de rafraîchissement
 * tant que l'onglet est caché. Un WebSocket/SSE pourra remplacer ce mécanisme
 * sans toucher aux composants : ils ne lisent que la donnée de cette requête.
 */
export function useOrderTracking(orderId: string) {
  return useQuery({
    queryKey: trackingKeys.order(orderId),
    queryFn: () => trackingApi.get(orderId),
    refetchInterval: (query) => {
      const seconds = query.state.data?.refreshSeconds

      return query.state.data?.available && seconds ? seconds * 1000 : false
    },
    refetchIntervalInBackground: false,
  })
}
