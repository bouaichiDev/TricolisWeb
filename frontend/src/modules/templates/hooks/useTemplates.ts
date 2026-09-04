import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { communicationRuleKeys } from '@/modules/communications/hooks/useCommunicationRules'
import { templatesApi, type TemplateFilters, type TemplatePayload } from '../api/templates.api'

export const templateKeys = {
  all: ['templates'] as const,
  lists: () => [...templateKeys.all, 'list'] as const,
  list: (filters: TemplateFilters) => [...templateKeys.lists(), filters] as const,
  detail: (id: string) => [...templateKeys.all, 'detail', id] as const,
}

export function useTemplateList(filters: TemplateFilters, enabled = true) {
  return useQuery({
    queryKey: templateKeys.list(filters),
    queryFn: () => templatesApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useTemplate(id: string | undefined) {
  return useQuery({
    queryKey: templateKeys.detail(id ?? ''),
    queryFn: () => templatesApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

/**
 * Modèles employables par une règle : actifs, du type et du canal voulus.
 *
 * Les documents en sont exclus par construction — une règle envoie un message,
 * et un modèle de facture n'a pas de canal par où partir.
 */
export function useTemplateOptions(filters: Partial<TemplateFilters> = {}) {
  return useQuery({
    queryKey: [...templateKeys.lists(), 'options', filters],
    queryFn: () => templatesApi.list({ page: 1, perPage: 100, isActive: true, ...filters }),
    staleTime: 60 * 1000,
  })
}

/**
 * Ce qu'une mutation de modèle invalide, et ce qu'elle laisse tranquille.
 *
 * Les listes de modèles et les sélecteurs de règles se rafraîchissent. Les
 * communications historiques **ne sont pas touchées** : leur contenu est un
 * instantané, et le §129 interdit de le recalculer depuis le modèle courant.
 */
function useTemplateMutation<TVariables>(
  mutationFn: (variables: TVariables) => Promise<unknown>,
  message: 'created' | 'updated' | 'deleted',
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: templateKeys.all })
      void queryClient.invalidateQueries({ queryKey: communicationRuleKeys.lists() })
      toast.success(t(`toast.${message}`))
    },
  })
}

export function useCreateTemplate() {
  return useTemplateMutation((payload: TemplatePayload) => templatesApi.create(payload), 'created')
}

export function useUpdateTemplate() {
  return useTemplateMutation(
    ({ id, ...payload }: Partial<TemplatePayload> & { id: string }) =>
      templatesApi.update(id, payload),
    'updated',
  )
}

export function useDeleteTemplate() {
  return useTemplateMutation((id: string) => templatesApi.remove(id), 'deleted')
}
