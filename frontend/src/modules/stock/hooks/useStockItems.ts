import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { ApiError } from '@/shared/api/errors'

import {
  stockItemsApi,
  type StockItemPayload,
  type StockItemUpdatePayload,
} from '../api/stock-items.api'
import { stockKeys } from './stockKeys'
import type { StockItemFilters } from '../types/stockFilters'

export function useStockItems(filters: StockItemFilters, enabled = true) {
  return useQuery({
    queryKey: stockKeys.itemList(filters),
    queryFn: () => stockItemsApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useCustomerStockItems(
  customerId: string,
  filters: StockItemFilters,
  enabled = true,
) {
  return useQuery({
    queryKey: stockKeys.itemsOfCustomer(customerId, filters),
    queryFn: () => stockItemsApi.byCustomer(customerId, filters),
    enabled: enabled && customerId !== '',
    placeholderData: (previous) => previous,
  })
}

export function useStockItem(id: string | undefined) {
  return useQuery({
    queryKey: stockKeys.item(id ?? ''),
    queryFn: () => stockItemsApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

/**
 * Référence physique d'un article de catalogue, s'il en a une.
 *
 * `catalogItemId` est `nullable` côté serveur : une marchandise peut arriver en
 * dépôt sans figurer au catalogue. L'inverse est vrai aussi — un article
 * catalogué n'est pas forcément suivi en stock, et la liste revient alors vide.
 */
export function useStockItemOfCatalogItem(catalogItemId: string, enabled = true) {
  const filters: StockItemFilters = { page: 1, perPage: 1, catalogItemId }

  const query = useQuery({
    queryKey: stockKeys.itemList(filters),
    queryFn: () => stockItemsApi.list(filters),
    enabled: enabled && catalogItemId !== '',
  })

  return { ...query, item: query.data?.data[0] ?? null }
}

/**
 * Met un article sous suivi de stock.
 *
 * Deux routes existent, et le choix n'est pas indifférent. Avec un client déjà
 * connu — depuis sa fiche, depuis un article de catalogue — la route imbriquée
 * refuse d'emblée un client d'une autre organisation. Sans, le client vient du
 * formulaire, et la route plate ne le lit qu'à un seul endroit : le corps.
 */
export function useCreateStockItem(customerId?: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: StockItemPayload) =>
      customerId === undefined || customerId === ''
        ? stockItemsApi.create(payload)
        : stockItemsApi.createForCustomer(customerId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: stockKeys.items() })
      toast.success(t('stock.itemCreated'))
    },
  })
}

export function useUpdateStockItem(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: StockItemUpdatePayload) => stockItemsApi.update(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: stockKeys.items() })
      // Le code d'article s'affiche dans les soldes et les mouvements : les
      // laisser en cache montrerait l'ancien à côté du nouveau.
      void queryClient.invalidateQueries({ queryKey: stockKeys.balances() })
      toast.success(t('toast.updated'))
    },
  })
}

/**
 * Suppression, refusée tant qu'un solde, un mouvement ou une réservation
 * s'y rattache.
 *
 * Les clés étrangères sont en `restrictOnDelete` : le refus vient de la base,
 * remonté en 409 avec une phrase rédigée. Elle est affichée telle quelle — un
 * message générique laisserait chercher laquelle des trois dépendances bloque.
 */
export function useDeleteStockItem() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => stockItemsApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: stockKeys.items() })
      toast.success(t('toast.deleted'))
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : t('errors.unexpected'))
    },
  })
}
