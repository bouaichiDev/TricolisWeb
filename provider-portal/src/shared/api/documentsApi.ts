import { toast } from 'sonner'

import { api } from './client'
import { errorMessage } from './errors'

/**
 * Un document d'exécution par son identifiant : le backend revérifie le
 * chemin métier (tournée, arrêt, service confirmé, preuve) jusqu'au
 * fournisseur courant. Jamais d'URL de stockage directe.
 */
export const documentsApi = {
  file: (documentId: string) => api.blob(`/documents/${documentId}`),
}

/** Téléchargement authentifié : un lien direct partirait sans le jeton. */
export async function downloadDocument(documentId: string, fileName: string): Promise<void> {
  try {
    const url = URL.createObjectURL(await documentsApi.file(documentId))
    const anchor = Object.assign(window.document.createElement('a'), { href: url, download: fileName })
    anchor.click()
    URL.revokeObjectURL(url)
  } catch (caught) {
    toast.error(errorMessage(caught))
  }
}
