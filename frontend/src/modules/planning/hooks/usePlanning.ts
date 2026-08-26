import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { tourKeys } from '@/modules/tours/hooks/useTours'

import { planningApi } from '../api/planning.api'
import type { PoolFilters } from '../types/pool'

export const planningKeys = {
  all: ['planning'] as const,
  pool: (filters: PoolFilters) => [...planningKeys.all, 'pool', filters] as const,
}

export function usePlanningPool(filters: PoolFilters) {
  return useQuery({
    queryKey: planningKeys.pool(filters),
    queryFn: () => planningApi.pool(filters),
    placeholderData: (previous) => previous,
  })
}

/**
 * Planifier une commande ou des services dans une tournée.
 *
 * Le pool **et** les tournées sont rafraîchis : ce qui entre dans une tournée
 * sort du pool, et laisser l'un des deux en arrière montrerait deux vérités.
 */
export function usePlanIntoTour() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({
      tourId,
      ...payload
    }: {
      tourId: string
      orderIds?: string[]
      orderServiceIds?: string[]
    }) => planningApi.plan(tourId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: planningKeys.all })
      void queryClient.invalidateQueries({ queryKey: tourKeys.all })
    },
  })
}
