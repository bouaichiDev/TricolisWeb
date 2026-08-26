import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { driversApi } from '../api/drivers.api'
import type { DriverFilters, DriverPayload } from '../types/driver'

export const driverKeys = {
  all: ['drivers'] as const,
  list: (filters: DriverFilters) => [...driverKeys.all, 'list', filters] as const,
  detail: (id: string) => [...driverKeys.all, 'detail', id] as const,
}

export function useDriverList(filters: DriverFilters, enabled = true) {
  return useQuery({
    queryKey: driverKeys.list(filters),
    queryFn: () => driversApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useDriver(id: string | null) {
  return useQuery({
    queryKey: driverKeys.detail(id ?? ''),
    queryFn: () => driversApi.get(id as string),
    enabled: id !== null && id !== '',
  })
}

export function useCreateDriver() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: DriverPayload) => driversApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: driverKeys.all })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateDriver() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, ...payload }: Partial<DriverPayload> & { id: string }) =>
      driversApi.update(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: driverKeys.all })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteDriver() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => driversApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: driverKeys.all })
      toast.success(t('toast.deleted'))
    },
  })
}
