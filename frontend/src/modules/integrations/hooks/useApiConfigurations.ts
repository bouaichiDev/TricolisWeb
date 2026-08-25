import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { apiConfigurationsApi } from '../api/apiConfigurations.api'
import type { ApiConfigurationFilters, ApiConfigurationPayload } from '../types/apiConfiguration'

export const apiConfigurationKeys = {
  all: ['api-configurations'] as const,
  list: (filters: ApiConfigurationFilters) => [...apiConfigurationKeys.all, filters] as const,
}

export function useApiConfigurationList(filters: ApiConfigurationFilters, enabled = true) {
  return useQuery({
    queryKey: apiConfigurationKeys.list(filters),
    queryFn: () => apiConfigurationsApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

function useConfigurationMutation<TVariables>(
  mutationFn: (variables: TVariables) => Promise<unknown>,
  message: 'created' | 'updated' | 'deleted',
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: apiConfigurationKeys.all })
      toast.success(t(`toast.${message}`))
    },
  })
}

export function useCreateApiConfiguration() {
  return useConfigurationMutation(
    (payload: ApiConfigurationPayload) => apiConfigurationsApi.create(payload),
    'created',
  )
}

export function useUpdateApiConfiguration() {
  return useConfigurationMutation(
    ({ id, ...payload }: Partial<ApiConfigurationPayload> & { id: string }) =>
      apiConfigurationsApi.update(id, payload),
    'updated',
  )
}

export function useDeleteApiConfiguration() {
  return useConfigurationMutation((id: string) => apiConfigurationsApi.remove(id), 'deleted')
}
