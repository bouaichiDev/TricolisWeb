import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import {
  communicationTemplatesApi,
  type CommunicationTemplateFilters,
  type CommunicationTemplatePayload,
} from '../api/communication-templates.api'

export const communicationTemplateKeys = {
  all: ['communication-templates'] as const,
  lists: () => [...communicationTemplateKeys.all, 'list'] as const,
  list: (filters: CommunicationTemplateFilters) =>
    [...communicationTemplateKeys.lists(), filters] as const,
  detail: (id: string) => [...communicationTemplateKeys.all, 'detail', id] as const,
}

export function useCommunicationTemplateList(
  filters: CommunicationTemplateFilters,
  enabled = true,
) {
  return useQuery({
    queryKey: communicationTemplateKeys.list(filters),
    queryFn: () => communicationTemplatesApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useCommunicationTemplate(id: string | undefined) {
  return useQuery({
    queryKey: communicationTemplateKeys.detail(id ?? ''),
    queryFn: () => communicationTemplatesApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

function useTemplateMutation<TVariables>(
  mutationFn: (variables: TVariables) => Promise<unknown>,
  message: 'created' | 'updated' | 'deleted',
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: communicationTemplateKeys.all })
      toast.success(t(`toast.${message}`))
    },
  })
}

export function useCreateCommunicationTemplate() {
  return useTemplateMutation(
    (payload: CommunicationTemplatePayload) => communicationTemplatesApi.create(payload),
    'created',
  )
}

export function useUpdateCommunicationTemplate() {
  return useTemplateMutation(
    ({ id, ...payload }: Partial<CommunicationTemplatePayload> & { id: string }) =>
      communicationTemplatesApi.update(id, payload),
    'updated',
  )
}

export function useDeleteCommunicationTemplate() {
  return useTemplateMutation(
    (id: string) => communicationTemplatesApi.remove(id),
    'deleted',
  )
}
