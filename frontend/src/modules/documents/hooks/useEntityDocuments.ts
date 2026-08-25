import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { entityDocumentsApi } from '../api/entityDocuments.api'
import type { DocumentUpload } from '../types/document'

export const entityDocumentKeys = {
  all: ['entity-documents'] as const,
  list: (entityType: string, entityId: string) =>
    [...entityDocumentKeys.all, entityType, entityId] as const,
}

export function useEntityDocuments(entityType: string, entityId: string, enabled = true) {
  return useQuery({
    queryKey: entityDocumentKeys.list(entityType, entityId),
    queryFn: () => entityDocumentsApi.list(entityType, entityId),
    enabled: enabled && entityId !== '',
  })
}

export function useUploadEntityDocument(entityType: string, entityId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: DocumentUpload) =>
      entityDocumentsApi.upload(entityType, entityId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: entityDocumentKeys.list(entityType, entityId),
      })
      toast.success(t('toast.created'))
    },
  })
}
