import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import {
  communicationRulesApi,
  type CommunicationRuleFilters,
  type CommunicationRulePayload,
} from '../api/communication-rules.api'

export const communicationRuleKeys = {
  all: ['communication-rules'] as const,
  lists: () => [...communicationRuleKeys.all, 'list'] as const,
  list: (filters: CommunicationRuleFilters) => [...communicationRuleKeys.lists(), filters] as const,
  detail: (id: string) => [...communicationRuleKeys.all, 'detail', id] as const,
}

export function useCommunicationRuleList(filters: CommunicationRuleFilters, enabled = true) {
  return useQuery({
    queryKey: communicationRuleKeys.list(filters),
    queryFn: () => communicationRulesApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useCommunicationRule(id: string | undefined) {
  return useQuery({
    queryKey: communicationRuleKeys.detail(id ?? ''),
    queryFn: () => communicationRulesApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

/**
 * Ce qu'une mutation de règle invalide.
 *
 * Les listes et sélecteurs de règles. **Pas les communications historiques** :
 * modifier une règle ne réécrit jamais ce qu'elle a déjà produit, et les
 * rafraîchir laisserait croire le contraire (§130).
 */
function useRuleMutation<TVariables>(
  mutationFn: (variables: TVariables) => Promise<unknown>,
  message: 'created' | 'updated' | 'deleted',
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: communicationRuleKeys.all })
      toast.success(t(`toast.${message}`))
    },
  })
}

export function useCreateCommunicationRule() {
  return useRuleMutation(
    (payload: CommunicationRulePayload) => communicationRulesApi.create(payload),
    'created',
  )
}

export function useUpdateCommunicationRule() {
  return useRuleMutation(
    ({ id, ...payload }: Partial<CommunicationRulePayload> & { id: string }) =>
      communicationRulesApi.update(id, payload),
    'updated',
  )
}

export function useDeleteCommunicationRule() {
  return useRuleMutation((id: string) => communicationRulesApi.remove(id), 'deleted')
}
