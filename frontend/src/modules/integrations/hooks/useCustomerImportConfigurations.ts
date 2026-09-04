import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { ApiError } from '@/shared/api/errors'

import { customerImportConfigurationsApi } from '../api/customer-import-configurations.api'
import { integrationKeys } from './integrationKeys'
import type {
  CustomerImportConfigurationFilters,
  CustomerImportConfigurationPayload,
} from '../types/customerIntegration'

export function useCustomerImportConfigurations(
  filters: CustomerImportConfigurationFilters,
  enabled = true,
) {
  return useQuery({
    queryKey: integrationKeys.importList(filters),
    queryFn: () => customerImportConfigurationsApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useCustomerImportConfigurationsOf(
  customerId: string,
  filters: CustomerImportConfigurationFilters,
) {
  return useQuery({
    queryKey: integrationKeys.importsOfCustomer(customerId, filters),
    queryFn: () => customerImportConfigurationsApi.byCustomer(customerId, filters),
    enabled: customerId !== '',
    placeholderData: (previous) => previous,
  })
}

export function useCustomerImportConfiguration(id: string | undefined) {
  return useQuery({
    queryKey: integrationKeys.import(id ?? ''),
    queryFn: () => customerImportConfigurationsApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

/**
 * Deux routes de création, selon ce qu'on sait du client.
 *
 * Depuis sa fiche, la route imbriquée refuse d'emblée un client d'une autre
 * organisation. Depuis la liste globale, le client vient du formulaire et la
 * route plate ne le lit qu'à un seul endroit.
 */
export function useCreateCustomerImportConfiguration(customerId?: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: CustomerImportConfigurationPayload) =>
      customerId === undefined || customerId === ''
        ? customerImportConfigurationsApi.create(payload)
        : customerImportConfigurationsApi.createForCustomer(customerId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: integrationKeys.imports() })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateCustomerImportConfiguration(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: Partial<Omit<CustomerImportConfigurationPayload, 'customerId'>>) =>
      customerImportConfigurationsApi.update(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: integrationKeys.imports() })
      toast.success(t('toast.updated'))
    },
  })
}

/**
 * Suppression.
 *
 * Une configuration d'import n'a pas d'historique d'exécution — il n'existe pas
 * de table `Import` — donc rien ne s'y oppose côté données. Un refus éventuel
 * du serveur est néanmoins affiché tel quel plutôt que traduit en message
 * générique.
 */
export function useDeleteCustomerImportConfiguration() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => customerImportConfigurationsApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: integrationKeys.imports() })
      toast.success(t('toast.deleted'))
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : t('errors.unexpected'))
    },
  })
}
