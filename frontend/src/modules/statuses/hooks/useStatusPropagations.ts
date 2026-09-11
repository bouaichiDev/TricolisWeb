import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { ApiError } from '@/shared/api/errors'

import { statusesApi } from '../api/statuses.api'
import type { StatusPropagationPayload } from '../types/status'
import { statusKeys } from './useStatuses'

const propagationsKey = [...statusKeys.all, 'propagations'] as const

/**
 * Règles de propagation, avec la hiérarchie qui les rend lisibles.
 *
 * La hiérarchie vient du serveur : elle dit qui contient qui, et c'est elle qui
 * décide des paires proposées. La recopier ici la ferait diverger du modèle au
 * premier lien ajouté.
 */
export function useStatusPropagations(enabled = true) {
  return useQuery({
    queryKey: propagationsKey,
    queryFn: () => statusesApi.propagations(),
    enabled,
    staleTime: 5 * 60 * 1000,
  })
}

function useRuleMutation<TInput>(
  run: (input: TInput) => Promise<unknown>,
  message: 'created' | 'updated' | 'deleted',
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: run,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: propagationsKey })
      toast.success(t(`toast.${message}`))
    },
    onError: (error) => {
      // Le refus du serveur dit pourquoi — entités non reliées, paire déjà
      // déclarée. Le remplacer par « erreur inattendue » ferait chercher.
      toast.error(
        error instanceof ApiError
          ? (Object.values(error.errors)[0]?.[0] ?? error.message)
          : t('errors.unexpected'),
      )
    },
  })
}

export function useCreateStatusPropagation() {
  return useRuleMutation(
    (payload: StatusPropagationPayload) => statusesApi.createPropagation(payload),
    'created',
  )
}

export function useUpdateStatusPropagation() {
  return useRuleMutation(
    ({ id, ...payload }: { id: string } & Partial<StatusPropagationPayload>) =>
      statusesApi.updatePropagation(id, payload),
    'updated',
  )
}

export function useDeleteStatusPropagation() {
  return useRuleMutation((id: string) => statusesApi.removePropagation(id), 'deleted')
}
