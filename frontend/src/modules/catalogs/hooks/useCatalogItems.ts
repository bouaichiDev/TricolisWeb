import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { catalogsApi, type CatalogItemPayload } from '../api/catalogs.api'
import type { CatalogItemFilters } from '../types/catalog'

export const catalogItemKeys = {
  all: ['catalog-items'] as const,
  lists: (customerId: string, catalogId: string) =>
    [...catalogItemKeys.all, 'list', customerId, catalogId] as const,
  list: (customerId: string, catalogId: string, filters: CatalogItemFilters) =>
    [...catalogItemKeys.lists(customerId, catalogId), filters] as const,
}

/**
 * Articles d'un catalogue.
 *
 * Toujours paginés, jamais chargés en entier : un catalogue client peut
 * compter des milliers d'articles, et le §51 interdit de tout charger d'un
 * coup.
 */
export function useCatalogItemList(
  customerId: string,
  catalogId: string,
  filters: CatalogItemFilters,
) {
  return useQuery({
    queryKey: catalogItemKeys.list(customerId, catalogId, filters),
    queryFn: () => catalogsApi.items(customerId, catalogId, filters),
    enabled: customerId !== '' && catalogId !== '',
    placeholderData: (previous) => previous,
  })
}

export function useCreateCatalogItem(customerId: string, catalogId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: CatalogItemPayload) =>
      catalogsApi.createItem(customerId, catalogId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: catalogItemKeys.lists(customerId, catalogId),
      })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateCatalogItem(customerId: string, catalogId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, ...payload }: CatalogItemPayload & { id: string }) =>
      catalogsApi.updateItem(customerId, catalogId, id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: catalogItemKeys.lists(customerId, catalogId),
      })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteCatalogItem(customerId: string, catalogId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (itemId: string) => catalogsApi.removeItem(customerId, catalogId, itemId),
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: catalogItemKeys.lists(customerId, catalogId),
      })
      toast.success(t('toast.deleted'))
    },
  })
}
