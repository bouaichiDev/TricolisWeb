import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { catalogApi } from '../api/catalogApi'
import type { CatalogFilters } from '../types'

export const catalogKeys = {
  all: ['catalog'] as const,
  catalogs: ['catalog', 'catalogs'] as const,
  items: (filters: CatalogFilters) => ['catalog', 'items', filters] as const,
  item: (id: string) => ['catalog', 'item', id] as const,
}

export const useCatalogs = () => useQuery({ queryKey: catalogKeys.catalogs, queryFn: catalogApi.catalogs, staleTime: 5 * 60 * 1000 })

export const useCatalogItems = (filters: CatalogFilters) =>
  useQuery({ queryKey: catalogKeys.items(filters), queryFn: () => catalogApi.items(filters), placeholderData: keepPreviousData })

export const useCatalogItem = (id: string) => useQuery({ queryKey: catalogKeys.item(id), queryFn: () => catalogApi.item(id) })

/**
 * Une écriture d'article (ajout, modification, (dés)activation) périme les
 * listes, la fiche, les compteurs et les choix des formulaires.
 */
function useCatalogWrite<TVariables, TResult>(mutationFn: (variables: TVariables) => Promise<TResult>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: catalogKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['stock'] })
      void queryClient.invalidateQueries({ queryKey: ['orders'] })
    },
  })
}

export const useCreateCatalogItem = () => useCatalogWrite(catalogApi.create)
export const useUpdateCatalogItem = () => useCatalogWrite(catalogApi.update)
export const useSetCatalogItemActive = () => useCatalogWrite(catalogApi.setActive)
