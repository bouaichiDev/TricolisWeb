import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { agenciesApi, type AgencyPayload } from '../api/agencies.api'
import { agencyKeys } from './agencyKeys'
import type { AgencyFilters } from '../types/agency'

export function useAgencyList(filters: AgencyFilters) {
  return useQuery({
    queryKey: agencyKeys.list(filters),
    queryFn: () => agenciesApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useAgency(id: string | undefined) {
  return useQuery({
    queryKey: agencyKeys.detail(id ?? ''),
    queryFn: () => agenciesApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

export function useCreateAgency() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: AgencyPayload) => agenciesApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: agencyKeys.lists() })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateAgency(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: Partial<AgencyPayload>) => agenciesApi.update(id, payload),
    onSuccess: (agency) => {
      queryClient.setQueryData(agencyKeys.detail(id), agency)
      void queryClient.invalidateQueries({ queryKey: agencyKeys.lists() })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteAgency() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => agenciesApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: agencyKeys.lists() })
      toast.success(t('toast.deleted'))
    },
  })
}
