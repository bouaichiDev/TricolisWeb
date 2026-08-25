import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'

import { api } from '@/shared/api/client'

/** Ce qu'un navigateur sait montrer sans rien installer. */
export type PreviewKind = 'image' | 'audio' | 'pdf' | null

/**
 * Ce que le fichier permet d'afficher, d'après son type déclaré.
 *
 * Le type vient du serveur, qui l'a relevé au dépôt — pas de l'extension du
 * nom, qu'un client renomme sans y penser.
 */
export function previewKind(mimeType: string): PreviewKind {
  if (mimeType.startsWith('image/')) return 'image'
  if (mimeType.startsWith('audio/')) return 'audio'
  if (mimeType === 'application/pdf') return 'pdf'

  return null
}

/**
 * URL locale d'un document, prête à être posée dans un `img` ou un `audio`.
 *
 * `GET /documents/{document}/download` est **authentifiée** : une URL mise
 * directement dans `src` partirait sans en-tête et reviendrait en 401. Le
 * fichier est donc récupéré par le client HTTP, puis republié au navigateur
 * sous forme d'URL d'objet.
 *
 * Cette URL est révoquée au démontage. Sans cela, chaque vignette laisserait
 * son fichier en mémoire jusqu'au rechargement de la page — une galerie de
 * photos de réclamations les y garderait toutes.
 */
export function useDocumentFile(documentId: string, enabled: boolean) {
  const query = useQuery({
    queryKey: ['document-file', documentId] as const,
    queryFn: () => api.blob(`/documents/${documentId}/download`),
    enabled: enabled && documentId !== '',
    // Un fichier depose ne change plus : le rappeler au moindre remontage
    // retelechargerait la meme image a chaque ouverture du tiroir.
    staleTime: 10 * 60 * 1000,
    retry: false,
  })

  const [url, setUrl] = useState<string | null>(null)

  useEffect(() => {
    if (query.data === undefined) {
      setUrl(null)

      return
    }

    const objectUrl = URL.createObjectURL(query.data)
    setUrl(objectUrl)

    return () => URL.revokeObjectURL(objectUrl)
  }, [query.data])

  return { url, isPending: query.isPending, isError: query.isError }
}
