import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import type { ResourceFilters } from '@/modules/drivers/types'
import { refusalMessage } from '@/shared/api/errors'

import { vehiclesApi } from '../api/vehiclesApi'

export function useVehicles(filters: ResourceFilters) {
  return useQuery({ queryKey: ['vehicles', filters], queryFn: () => vehiclesApi.list(filters), placeholderData: keepPreviousData })
}

/** Les types de véhicule de l'organisation, pour le formulaire. */
export function useVehicleTypes(enabled: boolean) {
  return useQuery({ queryKey: ['vehicle-types'], queryFn: vehiclesApi.types, enabled, staleTime: 5 * 60 * 1000 })
}

export function useVehicleStatus() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, active }: { id: string; active: boolean }) => vehiclesApi.setActive(id, active),
    onSuccess: (vehicle) => {
      void queryClient.invalidateQueries({ queryKey: ['vehicles'] })
      toast.success(vehicle.status === 'active' ? 'Véhicule réactivé.' : 'Véhicule désactivé.')
    },
    onError: (error) => toast.error(refusalMessage(error)),
  })
}
