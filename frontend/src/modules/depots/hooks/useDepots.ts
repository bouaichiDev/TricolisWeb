import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { depotsApi, type DepotPayload } from '../api/depots.api'

export const depotKeys = {
  all: ['depots'] as const,
  lists: (agencyId: string) => [...depotKeys.all, 'list', agencyId] as const,
  detail: (agencyId: string, depotId: string) =>
    [...depotKeys.all, 'detail', agencyId, depotId] as const,
}

export function useDepotList(agencyId: string, params: { page?: number; search?: string } = {}) {
  return useQuery({
    queryKey: [...depotKeys.lists(agencyId), params],
    queryFn: () => depotsApi.list(agencyId, { perPage: 25, ...params }),
    enabled: agencyId !== '',
    placeholderData: (previous) => previous,
  })
}

export function useDepot(agencyId: string, depotId: string | undefined) {
  return useQuery({
    queryKey: depotKeys.detail(agencyId, depotId ?? ''),
    queryFn: () => depotsApi.get(agencyId, depotId ?? ''),
    enabled: agencyId !== '' && depotId !== undefined && depotId !== '',
  })
}

export function useCreateDepot(agencyId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: DepotPayload) => depotsApi.create(agencyId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: depotKeys.lists(agencyId) })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateDepot(agencyId: string, depotId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: Partial<DepotPayload>) => depotsApi.update(agencyId, depotId, payload),
    onSuccess: (depot) => {
      queryClient.setQueryData(depotKeys.detail(agencyId, depotId), depot)
      void queryClient.invalidateQueries({ queryKey: depotKeys.lists(agencyId) })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteDepot(agencyId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (depotId: string) => depotsApi.remove(agencyId, depotId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: depotKeys.lists(agencyId) })
      toast.success(t('toast.deleted'))
    },
  })
}
