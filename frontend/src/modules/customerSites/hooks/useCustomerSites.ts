import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { customerSitesApi, type CustomerSitePayload } from '../api/customerSites.api'

export const customerSiteKeys = {
  all: ['customer-sites'] as const,
  lists: (customerId: string) => [...customerSiteKeys.all, 'list', customerId] as const,
  detail: (customerId: string, siteId: string) =>
    [...customerSiteKeys.all, 'detail', customerId, siteId] as const,
}

export function useCustomerSiteList(customerId: string, page = 1) {
  return useQuery({
    queryKey: [...customerSiteKeys.lists(customerId), page],
    queryFn: () => customerSitesApi.list(customerId, { page, perPage: 25 }),
    enabled: customerId !== '',
    placeholderData: (previous) => previous,
  })
}

export function useCustomerSite(customerId: string, siteId: string | undefined) {
  return useQuery({
    queryKey: customerSiteKeys.detail(customerId, siteId ?? ''),
    queryFn: () => customerSitesApi.get(customerId, siteId ?? ''),
    enabled: customerId !== '' && siteId !== undefined && siteId !== '',
  })
}

export function useCreateCustomerSite(customerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: CustomerSitePayload) => customerSitesApi.create(customerId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: customerSiteKeys.lists(customerId) })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateCustomerSite(customerId: string, siteId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: Partial<CustomerSitePayload>) =>
      customerSitesApi.update(customerId, siteId, payload),
    onSuccess: (site) => {
      queryClient.setQueryData(customerSiteKeys.detail(customerId, siteId), site)
      void queryClient.invalidateQueries({ queryKey: customerSiteKeys.lists(customerId) })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteCustomerSite(customerId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (siteId: string) => customerSitesApi.remove(customerId, siteId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: customerSiteKeys.lists(customerId) })
      toast.success(t('toast.deleted'))
    },
  })
}
