import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { addressesApi, type AddressCreatePayload, type AddressPayload } from '../api/addresses.api'

export const addressKeys = {
  all: ['addresses'] as const,
  lists: () => [...addressKeys.all, 'list'] as const,
  detail: (id: string) => [...addressKeys.all, 'detail', id] as const,
}

export function useAddressList(params: { page?: number; search?: string } = {}) {
  return useQuery({
    queryKey: [...addressKeys.lists(), params],
    queryFn: () => addressesApi.list({ perPage: 50, ...params }),
    placeholderData: (previous) => previous,
  })
}

export function useAddress(id: string | undefined) {
  return useQuery({
    queryKey: addressKeys.detail(id ?? ''),
    queryFn: () => addressesApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

export function useCreateAddress() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: AddressCreatePayload) => addressesApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: addressKeys.lists() })
    },
  })
}

export function useUpdateAddress(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: Partial<AddressPayload>) => addressesApi.update(id, payload),
    onSuccess: (address) => {
      queryClient.setQueryData(addressKeys.detail(id), address)
      void queryClient.invalidateQueries({ queryKey: addressKeys.lists() })
      toast.success(t('toast.updated'))
    },
  })
}
