import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { ordersApi } from '../api/ordersApi'
import type { OrderFilters } from '../types'

export const ordersKeys = {
  all: ['orders'] as const,
  list: (filters: OrderFilters) => ['orders', 'list', filters] as const,
  detail: (id: string) => ['orders', 'detail', id] as const,
  documents: (id: string) => ['orders', 'documents', id] as const,
  history: (id: string, page: number) => ['orders', 'history', id, page] as const,
  options: ['orders', 'form-options'] as const,
}

export function useOrders(filters: OrderFilters) {
  return useQuery({ queryKey: ordersKeys.list(filters), queryFn: () => ordersApi.list(filters), placeholderData: keepPreviousData })
}

export function useOrder(id: string, enabled = true) {
  return useQuery({ queryKey: ordersKeys.detail(id), queryFn: () => ordersApi.get(id), enabled: enabled && id !== '' })
}

export function useFormOptions() {
  return useQuery({ queryKey: ordersKeys.options, queryFn: ordersApi.formOptions, staleTime: 5 * 60 * 1000 })
}

export function useOrderDocuments(id: string, enabled: boolean) {
  return useQuery({ queryKey: ordersKeys.documents(id), queryFn: () => ordersApi.documents(id), enabled })
}

export function useOrderHistory(id: string, page: number, enabled: boolean) {
  return useQuery({ queryKey: ordersKeys.history(id, page), queryFn: () => ordersApi.history(id, page), enabled })
}

/**
 * Toute action sur une commande périme sa fiche, sa liste et son historique :
 * statut et capacités viennent du backend, on les redemande.
 */
export function useOrderMutation<TVariables, TResult>(mutationFn: (variables: TVariables) => Promise<TResult>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ordersKeys.all }),
  })
}
