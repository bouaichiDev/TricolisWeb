import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { pricingApi } from '../api/pricing.api'
import type {
  PrebillingFilters,
  PriceListFilters,
  PriceListPayload,
  PriceMatrixPayload,
  PriceRulePayload,
} from '../types/pricing'

export const pricingKeys = {
  all: ['pricing'] as const,
  lists: (filters: PriceListFilters) => [...pricingKeys.all, 'lists', filters] as const,
  list: (id: string) => [...pricingKeys.all, 'list', id] as const,
  prebilling: (filters: PrebillingFilters) => [...pricingKeys.all, 'prebilling', filters] as const,
}

export function usePriceLists(filters: PriceListFilters) {
  return useQuery({
    queryKey: pricingKeys.lists(filters),
    queryFn: () => pricingApi.lists(filters),
    placeholderData: (previous) => previous,
  })
}

export function usePriceList(id: string | null) {
  return useQuery({
    queryKey: pricingKeys.list(id ?? ''),
    queryFn: () => pricingApi.list(id as string),
    enabled: id !== null && id !== '',
  })
}

/**
 * La préfacturation.
 *
 * Chaque page recalcule les tarifs côté serveur : le résultat dépend des
 * barèmes, qui viennent peut-être d'être modifiés dans l'onglet d'à côté.
 */
export function usePrebilling(filters: PrebillingFilters) {
  return useQuery({
    queryKey: pricingKeys.prebilling(filters),
    queryFn: () => pricingApi.prebilling(filters),
    placeholderData: (previous) => previous,
    staleTime: 0,
  })
}

export function useCreatePriceList() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: PriceListPayload) => pricingApi.createList(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: pricingKeys.all })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdatePriceList() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<PriceListPayload> }) =>
      pricingApi.updateList(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: pricingKeys.all })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeletePriceList() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => pricingApi.removeList(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: pricingKeys.all })
      toast.success(t('toast.deleted'))
    },
  })
}

/**
 * Écrire une règle.
 *
 * Tout le domaine est invalidé, pas seulement le barème : une règle change ce
 * que la préfacturation calcule, et laisser l'ancien prix à l'écran ferait
 * douter du barème qu'on vient de corriger.
 */
export function useSaveRule(priceListId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, payload }: { id?: string; payload: PriceRulePayload }) =>
      id ? pricingApi.updateRule(id, payload) : pricingApi.createRule(priceListId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: pricingKeys.all })
      toast.success(t('toast.saved'))
    },
  })
}

export function useDeleteRule() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => pricingApi.removeRule(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: pricingKeys.all })
      toast.success(t('toast.deleted'))
    },
  })
}

export function useSaveMatrix(priceListId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, payload }: { id?: string; payload: PriceMatrixPayload }) =>
      id ? pricingApi.updateMatrix(id, payload) : pricingApi.createMatrix(priceListId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: pricingKeys.all })
      toast.success(t('toast.saved'))
    },
  })
}

export function useDeleteMatrix() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => pricingApi.removeMatrix(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: pricingKeys.all })
      toast.success(t('toast.deleted'))
    },
  })
}

/**
 * Vérifier une formule.
 *
 * Une mutation et non une requête : elle se déclenche quand l'utilisateur le
 * demande, et rien ne se met en cache — une formule à demi tapée n'a pas à
 * peupler le cache.
 */
export function useCheckFormula() {
  return useMutation({
    mutationFn: ({
      formula,
      variables,
    }: {
      formula: string
      variables?: Record<string, number | null>
    }) => pricingApi.checkFormula(formula, variables),
  })
}
