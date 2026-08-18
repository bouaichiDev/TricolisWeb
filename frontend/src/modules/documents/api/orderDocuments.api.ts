import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { Document, DocumentUpload } from '../types/document'

/**
 * Documents d'une commande.
 *
 * La commande est la seule entité à disposer d'une route imbriquée —
 * `GET|POST /orders/{order}/documents`. Le module Documents de la Phase 1 est
 * réutilisé tel quel : la liaison passe par `DocumentLink`, il n'existe pas de
 * table de fichiers propre aux commandes.
 */
export const orderDocumentsApi = {
  list: (orderId: string, page: number) =>
    api.get<ApiCollection<Document>>(`/orders/${orderId}/documents`, {
      query: { page, perPage: 25 },
    }),

  upload: (orderId: string, payload: DocumentUpload) => {
    const form = new FormData()

    form.append('file', payload.file)
    form.append('documentType', payload.documentType)
    form.append('status', payload.status)

    if (payload.referenceNumber) form.append('referenceNumber', payload.referenceNumber)
    if (payload.receivedAt) form.append('receivedAt', payload.receivedAt)

    return api
      .upload<ApiResource<Document>>(`/orders/${orderId}/documents`, form)
      .then((response) => response.data)
  },
}
