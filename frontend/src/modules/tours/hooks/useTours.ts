import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { toursApi } from '../api/tours.api'
import type { TourFilters, TourPayload } from '../types/tour'

export const tourKeys = {
  all: ['tours'] as const,
  list: (filters: TourFilters) => [...tourKeys.all, 'list', filters] as const,
  detail: (id: string) => [...tourKeys.all, 'detail', id] as const,
}

export function useTourList(filters: TourFilters) {
  return useQuery({
    queryKey: tourKeys.list(filters),
    queryFn: () => toursApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useTour(id: string | null) {
  return useQuery({
    queryKey: tourKeys.detail(id ?? ''),
    queryFn: () => toursApi.get(id as string),
    enabled: id !== null && id !== '',
  })
}

export function useCreateTour() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    // Une tournée naît toujours au brouillon : c'est le seul état depuis lequel
    // on peut encore y verser des commandes.
    mutationFn: (payload: TourPayload) => toursApi.create({ ...payload, status: 'draft' }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: tourKeys.all })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateTour(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: TourPayload) => toursApi.update(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: tourKeys.all })
      toast.success(t('toast.updated'))
    },
  })
}

export function useChangeTourStatus() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, status }: { id: string; status: string }) =>
      toursApi.changeStatus(id, status),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: tourKeys.all })
      toast.success(t('toast.updated'))
    },
  })
}
