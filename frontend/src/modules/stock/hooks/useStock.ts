import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { stockApi, type StockItemPayload, type StockMovementPayload } from '../api/stock.api'
import { stockKeys } from './stockKeys'
import type {
  StockBalanceFilters,
  StockItemFilters,
  StockLocationFilters,
  StockMovementFilters,
} from '../types/stock'

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
    queryFn: () => stockApi.items(filters),
    enabled: enabled && catalogItemId !== '',
  })

  return { ...query, item: query.data?.data[0] ?? null }
}

export function useStockItems(filters: StockItemFilters, enabled = true) {
  return useQuery({
    queryKey: stockKeys.itemList(filters),
    queryFn: () => stockApi.items(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useStockBalances(filters: StockBalanceFilters, enabled = true) {
  return useQuery({
    queryKey: stockKeys.balanceList(filters),
    queryFn: () => stockApi.balances(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useStockMovements(filters: StockMovementFilters, enabled = true) {
  return useQuery({
    queryKey: stockKeys.movementList(filters),
    queryFn: () => stockApi.movements(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useStockLocations(filters: StockLocationFilters, enabled = true) {
  return useQuery({
    queryKey: stockKeys.locationList(filters),
    queryFn: () => stockApi.locations(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

/** Met un article de catalogue sous suivi de stock. */
export function useCreateStockItem(customerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: StockItemPayload) => stockApi.createItem(customerId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: stockKeys.items() })
      toast.success(t('stock.itemCreated'))
    },
  })
}

/**
 * Enregistre un mouvement.
 *
 * **Aucune quantité ne se saisit directement.** `CreateStockMovementAction`
 * verrouille les soldes, contrôle la disponibilité, écrit le mouvement puis
 * recalcule — dans une transaction. Un solde modifié à la main n'aurait pas
 * d'histoire, et deux corrections concurrentes s'écraseraient.
 *
 * Soldes et historique changent tous deux : les deux listes sont invalidées.
 */
export function useCreateStockMovement() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: StockMovementPayload) => stockApi.createMovement(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: stockKeys.balances() })
      void queryClient.invalidateQueries({ queryKey: stockKeys.movements() })
      toast.success(t('stock.movementCreated'))
    },
  })
}
