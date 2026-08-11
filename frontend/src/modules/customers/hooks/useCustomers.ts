import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { customersApi, type CustomerPayload } from '../api/customers.api'
import { customerKeys } from './customerKeys'
import type { CustomerFilters } from '../types/customer'

export function useCustomerList(filters: CustomerFilters) {
  return useQuery({
    queryKey: customerKeys.list(filters),
    queryFn: () => customersApi.list(filters),
    // La page précédente reste affichée pendant le chargement de la suivante :
    // sans cela, la table clignote entre chaque page.
    placeholderData: (previous) => previous,
  })
}

export function useCustomer(id: string | undefined) {
  return useQuery({
    queryKey: customerKeys.detail(id ?? ''),
    queryFn: () => customersApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

export function useCreateCustomer() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: CustomerPayload) => customersApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: customerKeys.lists() })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateCustomer(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: Partial<CustomerPayload>) => customersApi.update(id, payload),
    onSuccess: (customer) => {
      queryClient.setQueryData(customerKeys.detail(id), customer)
      void queryClient.invalidateQueries({ queryKey: customerKeys.lists() })
      toast.success(t('toast.updated'))
    },
  })
}

export function useChangeCustomerStatus(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (status: string) => customersApi.changeStatus(id, status),
    onSuccess: (customer) => {
      queryClient.setQueryData(customerKeys.detail(id), customer)
      void queryClient.invalidateQueries({ queryKey: customerKeys.lists() })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteCustomer() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => customersApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: customerKeys.lists() })
      toast.success(t('toast.deleted'))
    },
  })
}
