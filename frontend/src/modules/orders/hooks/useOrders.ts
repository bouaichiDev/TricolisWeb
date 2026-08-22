import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { ordersApi, type StockLocationChoice } from '../api/orders.api'
import type { OrderFilters } from '../types/order'
import type {
  CreateOrderPayload,
  DuplicateOrderPayload,
  UpdateOrderPayload,
} from '../types/orderPayload'

export const orderKeys = {
  all: ['orders'] as const,
  lists: () => [...orderKeys.all, 'list'] as const,
  list: (filters: OrderFilters) => [...orderKeys.lists(), filters] as const,
  detail: (id: string) => [...orderKeys.all, 'detail', id] as const,
  history: (id: string, page: number) => [...orderKeys.all, 'history', id, page] as const,
  packageTree: (id: string) => [...orderKeys.all, 'package-tree', id] as const,
  stockPlan: (id: string) => [...orderKeys.all, 'stock-plan', id] as const,
}

export function useOrderList(filters: OrderFilters) {
  return useQuery({
    queryKey: orderKeys.list(filters),
    queryFn: () => ordersApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useOrder(id: string | undefined) {
  return useQuery({
    queryKey: orderKeys.detail(id ?? ''),
    queryFn: () => ordersApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

export function useOrderHistory(id: string, page: number) {
  return useQuery({
    queryKey: orderKeys.history(id, page),
    queryFn: () => ordersApi.history(id, page),
    enabled: id !== '',
    placeholderData: (previous) => previous,
  })
}

export function usePackageTree(id: string) {
  return useQuery({
    queryKey: orderKeys.packageTree(id),
    queryFn: () => ordersApi.packageTree(id),
    enabled: id !== '',
  })
}

export function useCreateOrder() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: CreateOrderPayload) => ordersApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: orderKeys.lists() })
      toast.success(t('orders.created'))
    },
  })
}

export function useUpdateOrder(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: UpdateOrderPayload) => ordersApi.update(id, payload),
    onSuccess: (order) => {
      queryClient.setQueryData(orderKeys.detail(id), order)
      void queryClient.invalidateQueries({ queryKey: orderKeys.lists() })
      // La confirmation sort la marchandise du stock : soldes et mouvements
      // affiches ailleurs sont perimes des cet instant.
      void queryClient.invalidateQueries({ queryKey: ['stock'] })
      void queryClient.invalidateQueries({ queryKey: orderKeys.stockPlan(id) })
      toast.success(t('toast.updated'))
    },
  })
}

/**
 * Changement de statut.
 *
 * Le statut visé vient d'`allowedTransitions`, calculé par le backend. Un 409
 * signale que l'état a changé entre-temps : le message métier est affiché tel
 * quel, il est rédigé pour être lu.
 */
export function useChangeOrderStatus(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (input: { status: string; stockLocations?: StockLocationChoice[] }) =>
      ordersApi.changeStatus(id, input.status, input.stockLocations ?? []),
    onSuccess: (order) => {
      queryClient.setQueryData(orderKeys.detail(id), order)
      void queryClient.invalidateQueries({ queryKey: orderKeys.lists() })
      toast.success(t('orders.statusChanged'))
    },
  })
}

export function useDuplicateOrder(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: DuplicateOrderPayload) => ordersApi.duplicate(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: orderKeys.lists() })
      toast.success(t('orders.duplicated'))
    },
  })
}

export function useDeleteOrder() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => ordersApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: orderKeys.lists() })
      toast.success(t('toast.deleted'))
    },
  })
}

/**
 * Ce que la confirmation sortirait du stock.
 *
 * Interrogé seulement quand la confirmation est visée : sur une commande déjà
 * terminée, la question ne se pose pas et la requête serait perdue.
 */
export function useOrderStockPlan(id: string, enabled: boolean) {
  return useQuery({
    queryKey: orderKeys.stockPlan(id),
    queryFn: () => ordersApi.stockPlan(id),
    enabled: enabled && id !== '',
  })
}
