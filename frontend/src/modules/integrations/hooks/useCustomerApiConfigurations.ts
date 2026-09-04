import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { ApiError } from '@/shared/api/errors'

import { customerApiConfigurationsApi } from '../api/customer-api-configurations.api'
import { integrationKeys } from './integrationKeys'
import type {
  CustomerApiConfigurationFilters,
  CustomerApiConfigurationPayload,
} from '../types/customerIntegration'

export function useCustomerApiConfigurations(
  filters: CustomerApiConfigurationFilters,
  enabled = true,
) {
  return useQuery({
    queryKey: integrationKeys.apiAccessList(filters),
    queryFn: () => customerApiConfigurationsApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useCustomerApiConfigurationsOf(
  customerId: string,
  filters: CustomerApiConfigurationFilters,
) {
  return useQuery({
    queryKey: integrationKeys.apiAccessOfCustomer(customerId, filters),
    queryFn: () => customerApiConfigurationsApi.byCustomer(customerId, filters),
    enabled: customerId !== '',
    placeholderData: (previous) => previous,
  })
}

export function useCustomerApiConfiguration(id: string | undefined) {
  return useQuery({
    queryKey: integrationKeys.apiAccessDetail(id ?? ''),
    queryFn: () => customerApiConfigurationsApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

function useInvalidateApiAccess() {
  const queryClient = useQueryClient()

  return () => void queryClient.invalidateQueries({ queryKey: integrationKeys.apiAccess() })
}

/**
 * Crée un accès API et **reçoit la clé en clair**.
 *
 * C'est l'un des deux seuls appels qui la voient. Le résultat n'est pas écrit
 * au cache : `onSuccess` n'invalide que la liste, et la clé repart à l'appelant,
 * qui l'affiche puis la jette. Une entrée `setQueryData` la ferait survivre à la
 * fermeture du dialogue, et un rechargement de page la ressortirait — ce que le
 * §22 interdit.
 */
export function useCreateCustomerApiConfiguration(customerId?: string) {
  const invalidate = useInvalidateApiAccess()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: CustomerApiConfigurationPayload) =>
      customerId === undefined || customerId === ''
        ? customerApiConfigurationsApi.create(payload)
        : customerApiConfigurationsApi.createForCustomer(customerId, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateCustomerApiConfiguration(id: string) {
  const invalidate = useInvalidateApiAccess()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: Partial<Omit<CustomerApiConfigurationPayload, 'customerId'>>) =>
      customerApiConfigurationsApi.update(id, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.updated'))
    },
  })
}

/**
 * Renouvelle la clé.
 *
 * L'ancienne est invalidée **immédiatement** côté serveur : ce n'est pas une
 * opération à déclencher sans confirmation, et l'appelant en pose une. La
 * nouvelle clé suit le même chemin que celle de la création — affichée une
 * fois, jamais cachée.
 */
export function useRotateCustomerApiKey() {
  const invalidate = useInvalidateApiAccess()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => customerApiConfigurationsApi.rotateKey(id),
    onSuccess: () => {
      invalidate()
      toast.success(t('integrations.api.keyRotated'))
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : t('errors.unexpected'))
    },
  })
}

export function useDeleteCustomerApiConfiguration() {
  const invalidate = useInvalidateApiAccess()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => customerApiConfigurationsApi.remove(id),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.deleted'))
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : t('errors.unexpected'))
    },
  })
}
