import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { accessRequestsApi } from '../api/accessRequests.api'
import type { AccessRequestPayload, AccessRequestStatus } from '../types/accessRequest'

export const accessRequestKeys = {
  all: ['access-requests'] as const,
  list: (filters: unknown) => [...accessRequestKeys.all, 'list', filters] as const,
}

export function useAccessRequestList(filters: {
  page: number
  perPage: number
  status?: AccessRequestStatus
}) {
  return useQuery({
    queryKey: accessRequestKeys.list(filters),
    queryFn: () => accessRequestsApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

/**
 * Le dépôt public.
 *
 * Pas de `queryClient` ici : la page qui l'appelle n'a pas de session, et il
 * n'y a rien à rafraîchir — la liste des demandes appartient à la plateforme.
 */
export function useSubmitAccessRequest() {
  return useMutation({
    mutationFn: (payload: AccessRequestPayload) => accessRequestsApi.submit(payload),
  })
}

/**
 * Trancher une demande.
 *
 * Les deux décisions partagent ce hook : elles invalident la même liste et
 * disent la même chose une fois faites — seul le verbe change.
 */
function useDecision(
  mutationFn: (variables: { id: string; note?: string }) => Promise<unknown>,
  messageKey: string,
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: accessRequestKeys.all })
      toast.success(t(messageKey))
    },
  })
}

export function useApproveAccessRequest() {
  return useDecision(
    ({ id, note }) => accessRequestsApi.approve(id, note),
    'accessRequests.approved',
  )
}

export function useRejectAccessRequest() {
  return useDecision(
    ({ id, note }) => accessRequestsApi.reject(id, note),
    'accessRequests.rejected',
  )
}
