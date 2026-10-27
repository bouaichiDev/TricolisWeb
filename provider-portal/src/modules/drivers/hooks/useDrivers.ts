import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { refusalMessage } from '@/shared/api/errors'

import { driversApi } from '../api/driversApi'
import type { ResourceFilters } from '../types'

export function useDrivers(filters: ResourceFilters) {
  return useQuery({ queryKey: ['drivers', filters], queryFn: () => driversApi.list(filters), placeholderData: keepPreviousData })
}

/** Désactiver / réactiver : la liste se recharge, un refus s'affiche. */
export function useDriverStatus() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, active }: { id: string; active: boolean }) => driversApi.setActive(id, active),
    onSuccess: (driver) => {
      void queryClient.invalidateQueries({ queryKey: ['drivers'] })
      toast.success(driver.status === 'active' ? 'Chauffeur réactivé.' : 'Chauffeur désactivé.')
    },
    onError: (error) => toast.error(refusalMessage(error)),
  })
}
