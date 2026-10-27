import { useQuery } from '@tanstack/react-query'

import { documentsApi } from '@/shared/api/documentsApi'
import { useObjectUrl } from '@/shared/hooks/useObjectUrl'

import { ordersApi } from '../api/ordersApi'

export type PreviewKind = 'image' | 'pdf' | null

/** Ce que le navigateur sait montrer lui-même ; le reste se télécharge. */
export function previewKind(mimeType: string): PreviewKind {
  if (mimeType.startsWith('image/')) return 'image'
  if (mimeType === 'application/pdf') return 'pdf'

  return null
}

/**
 * Le fichier d'un document, en URL locale, pour une vignette ou un aperçu.
 *
 * Récupéré par la route de la **commande** quand elle est connue, sinon par
 * `/documents/{id}/download` (jeton compris, chemin métier revérifié) : un
 * `<img src>` direct partirait sans authentification. Mis en cache : la
 * vignette et l'aperçu partagent le même téléchargement.
 */
export function useOrderDocumentFile(orderId: string | undefined, documentId: string, enabled: boolean) {
  const query = useQuery({
    queryKey: ['documents', 'file', orderId ?? 'direct', documentId],
    queryFn: () => fetchDocumentFile(orderId, documentId),
    enabled,
    staleTime: 10 * 60 * 1000,
    retry: false,
  })
  const url = useObjectUrl(query.data)

  return { url, isError: query.isError }
}

/** Le contenu d'un document, par la commande si elle est connue. */
export function fetchDocumentFile(orderId: string | undefined, documentId: string): Promise<Blob> {
  return orderId ? ordersApi.downloadDocument(orderId, documentId) : documentsApi.download(documentId)
}
