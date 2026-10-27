import { useQuery } from '@tanstack/react-query'

import { ApiError } from '@/shared/api/errors'

import { publicTrackingApi } from '../api/publicTrackingApi'

/**
 * Le suivi public, rafraîchi tant que la commande évolue. Un lien refusé
 * (404) ne se redemande pas : il ne redeviendra pas valable tout seul.
 */
export function usePublicTracking(token: string) {
  return useQuery({
    queryKey: ['public-tracking', token],
    queryFn: () => publicTrackingApi.get(token),
    retry: (count, error) => !(error instanceof ApiError && error.status < 500) && count < 2,
    refetchInterval: (query) => {
      if (query.state.error) return false
      const seconds = query.state.data?.refreshSeconds

      return seconds ? seconds * 1000 : false
    },
  })
}
