import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { catalogsApi, type CatalogPayload } from '../api/catalogs.api'
import type { CatalogFilters } from '../types/catalog'

/**
 * Fabrique de clés des catalogues.
 *
 * Le client fait partie de la clé : deux clients ont des catalogues distincts,
 * et les confondre ferait apparaître ceux de l'un dans la fiche de l'autre.
 */
export const catalogKeys = {
  all: ['catalogs'] as const,
  lists: (customerId: string) => [...catalogKeys.all, 'list', customerId] as const,
  list: (customerId: string, filters: CatalogFilters) =>
    [...catalogKeys.lists(customerId), filters] as const,
  detail: (customerId: string, catalogId: string) =>
    [...catalogKeys.all, 'detail', customerId, catalogId] as const,
}

/**
 * Catalogues d'un client.
 *
 * `enabled` permet de ne rien demander lorsque la capacité `catalogEnabled` du
 * client est désactivée : la réponse serait vide, et l'appel n'aurait servi
 * qu'à faire patienter.
 */
export function useCatalogList(customerId: string, filters: CatalogFilters, enabled = true) {
  return useQuery({
    queryKey: catalogKeys.list(customerId, filters),
    queryFn: () => catalogsApi.list(customerId, filters),
    enabled: enabled && customerId !== '',
    placeholderData: (previous) => previous,
  })
}

export function useCatalog(customerId: string, catalogId: string | undefined) {
  return useQuery({
    queryKey: catalogKeys.detail(customerId, catalogId ?? ''),
    queryFn: () => catalogsApi.get(customerId, catalogId ?? ''),
    enabled: customerId !== '' && catalogId !== undefined && catalogId !== '',
  })
}

export function useCreateCatalog(customerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: CatalogPayload) => catalogsApi.create(customerId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: catalogKeys.lists(customerId) })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateCatalog(customerId: string, catalogId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: Partial<CatalogPayload>) =>
      catalogsApi.update(customerId, catalogId, payload),
    onSuccess: (catalog) => {
      queryClient.setQueryData(catalogKeys.detail(customerId, catalogId), catalog)
      void queryClient.invalidateQueries({ queryKey: catalogKeys.lists(customerId) })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteCatalog(customerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (catalogId: string) => catalogsApi.remove(customerId, catalogId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: catalogKeys.lists(customerId) })
      toast.success(t('toast.deleted'))
    },
  })
}
