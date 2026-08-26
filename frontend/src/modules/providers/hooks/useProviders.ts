import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { providersApi } from '../api/providers.api'
import type { ProviderFilters, ProviderPayload } from '../types/provider'

export const providerKeys = {
  all: ['providers'] as const,
  list: (filters: ProviderFilters) => [...providerKeys.all, 'list', filters] as const,
  detail: (id: string) => [...providerKeys.all, 'detail', id] as const,
}

export function useProviderList(filters: ProviderFilters) {
  return useQuery({
    queryKey: providerKeys.list(filters),
    queryFn: () => providersApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useProvider(id: string | null) {
  return useQuery({
    queryKey: providerKeys.detail(id ?? ''),
    queryFn: () => providersApi.get(id as string),
    enabled: id !== null && id !== '',
  })
}

/** Fournisseurs actifs, pour les listes déroulantes des chauffeurs et véhicules. */
export function useProviderOptions() {
  const query = useQuery({
    queryKey: providerKeys.list({ page: 1, perPage: 100, status: 'active' }),
    queryFn: () => providersApi.list({ page: 1, perPage: 100, status: 'active' }),
    staleTime: 5 * 60 * 1000,
  })

  return {
    isLoading: query.isPending,
    options: (query.data?.data ?? []).map((provider) => ({
      value: provider.id,
      label: provider.name,
      hint: provider.code,
    })),
  }
}

export function useCreateProvider() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: ProviderPayload) => providersApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: providerKeys.all })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateProvider() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, ...payload }: Partial<ProviderPayload> & { id: string }) =>
      providersApi.update(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: providerKeys.all })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteProvider() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => providersApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: providerKeys.all })
      toast.success(t('toast.deleted'))
    },
  })
}
