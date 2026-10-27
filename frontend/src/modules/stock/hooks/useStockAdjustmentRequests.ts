import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import {
  stockAdjustmentRequestsApi,
  type ApproveAdjustmentPayload,
  type RejectAdjustmentPayload,
} from '../api/stock-adjustment-requests.api'
import { stockKeys } from './stockKeys'
import type { StockAdjustmentRequestFilters } from '../types/stockAdjustment'

export function useStockAdjustmentRequests(filters: StockAdjustmentRequestFilters) {
  return useQuery({
    queryKey: stockKeys.adjustmentList(filters),
    queryFn: () => stockAdjustmentRequestsApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useCustomerStockAdjustmentRequests(
  customerId: string,
  filters: StockAdjustmentRequestFilters,
) {
  return useQuery({
    queryKey: stockKeys.adjustmentsOfCustomer(customerId, filters),
    queryFn: () => stockAdjustmentRequestsApi.ofCustomer(customerId, filters),
    placeholderData: (previous) => previous,
  })
}

export function useStockAdjustmentRequest(id: string | undefined) {
  return useQuery({
    queryKey: stockKeys.adjustment(id ?? ''),
    queryFn: () => stockAdjustmentRequestsApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

/**
 * Ce qu'une décision périme.
 *
 * Accepter écrit un mouvement : les soldes, l'historique des mouvements, la
 * fiche de l'article et le stock du client changent. Un refus ne touche que
 * les demandes, mais relire le reste ne coûte rien et évite de distinguer.
 * Un 409 invalide aussi : la demande a été tranchée ailleurs, ou le stock a
 * bougé — l'écran doit relire plutôt que laisser réessayer.
 */
function useInvalidateAdjustments() {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: stockKeys.adjustments() })
    void queryClient.invalidateQueries({ queryKey: stockKeys.balances() })
    void queryClient.invalidateQueries({ queryKey: stockKeys.movements() })
    void queryClient.invalidateQueries({ queryKey: stockKeys.items() })
  }
}

/** Le message d'erreur est affiché par le dialogue appelant. */
export function useApproveStockAdjustment(id: string) {
  const invalidate = useInvalidateAdjustments()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: ApproveAdjustmentPayload) =>
      stockAdjustmentRequestsApi.approve(id, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('stock.adjustments.approved'))
    },
    onError: invalidate,
  })
}

export function useRejectStockAdjustment(id: string) {
  const invalidate = useInvalidateAdjustments()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: RejectAdjustmentPayload) =>
      stockAdjustmentRequestsApi.reject(id, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('stock.adjustments.rejected'))
    },
    onError: invalidate,
  })
}
