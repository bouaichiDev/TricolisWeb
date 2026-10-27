import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { stockApi } from '../api/stockApi'
import type { StockFilters } from '../types'

export const stockKeys = {
  all: ['stock'] as const,
  summary: ['stock', 'summary'] as const,
  items: (filters: StockFilters) => ['stock', 'items', filters] as const,
  item: (id: string) => ['stock', 'item', id] as const,
  balances: (id: string) => ['stock', 'balances', id] as const,
  movements: (filters: StockFilters) => ['stock', 'movements', filters] as const,
  reservations: (filters: StockFilters) => ['stock', 'reservations', filters] as const,
}

export const useStockSummary = () => useQuery({ queryKey: stockKeys.summary, queryFn: stockApi.summary })

export const useStockItems = (filters: StockFilters) =>
  useQuery({ queryKey: stockKeys.items(filters), queryFn: () => stockApi.items(filters), placeholderData: keepPreviousData })

export const useStockItem = (id: string) => useQuery({ queryKey: stockKeys.item(id), queryFn: () => stockApi.item(id) })

export const useStockBalances = (id: string) => useQuery({ queryKey: stockKeys.balances(id), queryFn: () => stockApi.balances(id) })

export const useStockMovements = (filters: StockFilters, enabled = true) =>
  useQuery({ queryKey: stockKeys.movements(filters), queryFn: () => stockApi.movements(filters), placeholderData: keepPreviousData, enabled })

export const useStockReservations = (filters: StockFilters, enabled = true) =>
  useQuery({ queryKey: stockKeys.reservations(filters), queryFn: () => stockApi.reservations(filters), placeholderData: keepPreviousData, enabled })

/** Créer ou corriger un article périme tout le stock affiché : on le redemande. */
export function useStockMutation<TVariables, TResult>(mutationFn: (variables: TVariables) => Promise<TResult>) {
  const queryClient = useQueryClient()

  return useMutation({ mutationFn, onSuccess: () => queryClient.invalidateQueries({ queryKey: stockKeys.all }) })
}
