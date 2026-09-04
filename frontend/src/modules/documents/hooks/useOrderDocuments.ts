import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { orderDocumentsApi } from '../api/orderDocuments.api'
import type { DocumentUpload } from '../types/document'

export const orderDocumentKeys = {
  all: ['order-documents'] as const,
  list: (orderId: string, page: number) => [...orderDocumentKeys.all, orderId, page] as const,
  lists: (orderId: string) => [...orderDocumentKeys.all, orderId] as const,
}

export function useOrderDocuments(orderId: string, page: number) {
  return useQuery({
    queryKey: orderDocumentKeys.list(orderId, page),
    queryFn: () => orderDocumentsApi.list(orderId, page),
    enabled: orderId !== '',
    placeholderData: (previous) => previous,
  })
}

export function useUploadOrderDocument(orderId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: DocumentUpload) => orderDocumentsApi.upload(orderId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: orderDocumentKeys.lists(orderId) })
      toast.success(t('toast.created'))
    },
  })
}
