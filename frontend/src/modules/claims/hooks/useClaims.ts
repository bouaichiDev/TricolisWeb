import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { claimsApi } from '../api/claims.api'
import type { ClaimFilters, ClaimPayload, ClaimUpdatePayload } from '../types/claim'

export const claimKeys = {
  all: ['claims'] as const,
  lists: () => [...claimKeys.all, 'list'] as const,
  list: (filters: ClaimFilters) => [...claimKeys.lists(), filters] as const,
  byOrder: (orderId: string) => [...claimKeys.all, 'order', orderId] as const,
  orderList: (orderId: string, filters: ClaimFilters) =>
    [...claimKeys.byOrder(orderId), filters] as const,
  detail: (id: string) => [...claimKeys.all, 'detail', id] as const,
}

/**
 * Détail d'une réclamation.
 *
 * `ClaimListResource` **n'expose pas** `description`, `cause`, `decision`,
 * `followUp` ni `orderServiceId` : ouvrir un formulaire depuis une ligne de
 * liste les afficherait vides, et enregistrer les effacerait. Le détail est
 * donc rechargé avant toute modification.
 */
export function useClaim(id: string | null) {
  return useQuery({
    queryKey: claimKeys.detail(id ?? ''),
    queryFn: () => claimsApi.get(id ?? ''),
    enabled: id !== null && id !== '',
  })
}

export function useClaimList(filters: ClaimFilters) {
  return useQuery({
    queryKey: claimKeys.list(filters),
    queryFn: () => claimsApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

/** Réclamations d'une commande. Chargées à l'ouverture de l'onglet. */
export function useOrderClaims(orderId: string, filters: ClaimFilters, enabled = true) {
  return useQuery({
    queryKey: claimKeys.orderList(orderId, filters),
    queryFn: () => claimsApi.byOrder(orderId, filters),
    enabled: enabled && orderId !== '',
    placeholderData: (previous) => previous,
  })
}

/**
 * Toute écriture invalide **les deux** listes.
 *
 * Une réclamation créée depuis une commande apparaît aussi dans la liste
 * globale ; n'invalider que celle qu'on regarde laisserait l'autre fausse.
 */
function useClaimMutation<TVariables>(
  mutationFn: (variables: TVariables) => Promise<unknown>,
  message: 'created' | 'updated' | 'deleted',
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: claimKeys.all })
      toast.success(t(`toast.${message}`))
    },
  })
}

export function useCreateClaim(customerId: string) {
  return useClaimMutation(
    (payload: Omit<ClaimPayload, 'customerId'>) =>
      claimsApi.createForCustomer(customerId, payload),
    'created',
  )
}

export function useUpdateClaim() {
  return useClaimMutation(
    ({ id, ...payload }: ClaimUpdatePayload & { id: string }) => claimsApi.update(id, payload),
    'updated',
  )
}

export function useDeleteClaim() {
  return useClaimMutation((id: string) => claimsApi.remove(id), 'deleted')
}
