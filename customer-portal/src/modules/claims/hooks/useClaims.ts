import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { claimsApi } from '../api/claimsApi'
import type { ClaimFilters } from '../types'

export const claimsKeys = {
  all: ['claims'] as const,
  list: (filters: ClaimFilters) => ['claims', 'list', filters] as const,
  detail: (id: string) => ['claims', 'detail', id] as const,
  documents: (id: string) => ['claims', 'documents', id] as const,
}

export function useClaims(filters: ClaimFilters, enabled = true) {
  return useQuery({ queryKey: claimsKeys.list(filters), queryFn: () => claimsApi.list(filters), placeholderData: keepPreviousData, enabled })
}

export function useClaim(id: string) {
  return useQuery({ queryKey: claimsKeys.detail(id), queryFn: () => claimsApi.get(id) })
}

export function useClaimDocuments(id: string) {
  return useQuery({ queryKey: claimsKeys.documents(id), queryFn: () => claimsApi.documents(id) })
}

/** Toute écriture périme la liste, le détail et les pièces : on les redemande. */
export function useClaimMutation<TVariables, TResult>(mutationFn: (variables: TVariables) => Promise<TResult>) {
  const queryClient = useQueryClient()

  return useMutation({ mutationFn, onSuccess: () => queryClient.invalidateQueries({ queryKey: claimsKeys.all }) })
}
