import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { Document, DocumentUpload } from '../types/document'

/**
 * Documents rattachés à n'importe quelle entité.
 *
 * `DocumentLink` est polymorphe : une réclamation, un client, un site peuvent
 * porter des pièces. Seule la commande avait une route imbriquée ; le filtre
 * `entityType` / `entityId` de `GET /documents` couvre le reste.
 */
export const entityDocumentsApi = {
  list: (entityType: string, entityId: string) =>
    api.get<ApiCollection<Document>>('/documents', {
      query: { entityType, entityId, page: 1, perPage: 50 },
    }),

  upload: (entityType: string, entityId: string, payload: DocumentUpload) => {
    const form = new FormData()

    form.append('file', payload.file)
    form.append('documentType', payload.documentType)
    form.append('status', payload.status)
    form.append('entityType', entityType)
    form.append('entityId', entityId)

    return api
      .upload<ApiResource<Document>>('/documents', form)
      .then((response) => response.data)
  },
}
