import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { organizationKeys } from './organizationKeys'
import { organizationsApi, type OrganizationPayload } from '../api/organizations.api'
import type { OrganizationFilters } from '../types/organization'

export function useOrganizationList(filters: OrganizationFilters) {
  return useQuery({
    queryKey: organizationKeys.list(filters),
    queryFn: () => organizationsApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useOrganization(id: string | undefined) {
  return useQuery({
    queryKey: organizationKeys.detail(id ?? ''),
    queryFn: () => organizationsApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

export function useCreateOrganization() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: OrganizationPayload) => organizationsApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: organizationKeys.lists() })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateOrganization(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: Partial<OrganizationPayload>) => organizationsApi.update(id, payload),
    onSuccess: (organization) => {
      queryClient.setQueryData(organizationKeys.detail(id), organization)
      void queryClient.invalidateQueries({ queryKey: organizationKeys.lists() })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteOrganization() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => organizationsApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: organizationKeys.lists() })
      toast.success(t('toast.deleted'))
    },
  })
}
