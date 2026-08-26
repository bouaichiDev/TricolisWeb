import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { vehiclesApi } from '../api/vehicles.api'
import type { VehicleFilters, VehiclePayload } from '../types/vehicle'

export const vehicleKeys = {
  all: ['vehicles'] as const,
  list: (filters: VehicleFilters) => [...vehicleKeys.all, 'list', filters] as const,
  detail: (id: string) => [...vehicleKeys.all, 'detail', id] as const,
}

export function useVehicleList(filters: VehicleFilters, enabled = true) {
  return useQuery({
    queryKey: vehicleKeys.list(filters),
    queryFn: () => vehiclesApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useVehicle(id: string | null) {
  return useQuery({
    queryKey: vehicleKeys.detail(id ?? ''),
    queryFn: () => vehiclesApi.get(id as string),
    enabled: id !== null && id !== '',
  })
}

export function useCreateVehicle() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: VehiclePayload) => vehiclesApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: vehicleKeys.all })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateVehicle() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, ...payload }: Partial<VehiclePayload> & { id: string }) =>
      vehiclesApi.update(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: vehicleKeys.all })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteVehicle() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => vehiclesApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: vehicleKeys.all })
      toast.success(t('toast.deleted'))
    },
  })
}
