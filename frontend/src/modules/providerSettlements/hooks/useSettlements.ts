import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { settlementsApi } from '../api/settlements.api'
import type {
  SettleableServiceFilters,
  SettlementFilters,
  SettlementPayload,
} from '../types/settlement'

export const settlementKeys = {
  all: ['provider-settlements'] as const,
  list: (filters: SettlementFilters) => [...settlementKeys.all, 'list', filters] as const,
  detail: (id: string) => [...settlementKeys.all, 'detail', id] as const,
  settleable: (providerId: string, filters: SettleableServiceFilters) =>
    ['settleable-services', providerId, filters] as const,
}

export function useSettlementList(filters: SettlementFilters) {
  return useQuery({
    queryKey: settlementKeys.list(filters),
    queryFn: () => settlementsApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useSettlement(id: string | null) {
  return useQuery({
    queryKey: settlementKeys.detail(id ?? ''),
    queryFn: () => settlementsApi.get(id as string),
    enabled: id !== null && id !== '',
  })
}

/**
 * Les prestations à régler à un fournisseur.
 *
 * Sans fournisseur désigné, aucune requête : la route n'existe que sous un
 * fournisseur, et « tout ce qui reste à régler » ne dirait pas à qui.
 */
export function useSettleableServices(providerId: string, filters: SettleableServiceFilters) {
  return useQuery({
    queryKey: settlementKeys.settleable(providerId, filters),
    queryFn: () => settlementsApi.settleableServices(providerId, filters),
    enabled: providerId !== '',
    placeholderData: (previous) => previous,
  })
}

export function useCreateSettlement(providerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: SettlementPayload) => settlementsApi.createFor(providerId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: settlementKeys.all })
      // Les prestations retenues ne sont plus a regler : le selecteur doit
      // cesser de les proposer.
      void queryClient.invalidateQueries({ queryKey: ['settleable-services'] })
      toast.success(t('toast.created'))
    },
  })
}

export function useDeleteSettlement() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => settlementsApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: settlementKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['settleable-services'] })
      toast.success(t('toast.deleted'))
    },
  })
}

export function useRemoveSettlementLine(settlementId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (lineId: string) => settlementsApi.removeLine(settlementId, lineId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: settlementKeys.detail(settlementId) })
      void queryClient.invalidateQueries({ queryKey: ['settleable-services'] })
      toast.success(t('toast.deleted'))
    },
  })
}
