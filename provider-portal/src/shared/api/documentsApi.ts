import { toast } from 'sonner'

import { api } from './client'
import { errorMessage } from './errors'

/**
 * Un document d'exécution par son identifiant : le backend revérifie le
 * chemin métier (tournée, arrêt, service acquis, preuve) jusqu'au
 * fournisseur courant. Jamais d'URL de stockage directe.
 */
export const documentsApi = {
  file: (documentId: string) => api.blob(`/documents/${documentId}`),
}

const EXTENSIONS: Record<string, string> = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp', 'application/pdf': 'pdf' }

/**
 * Enregistre un fichier reçu. Sans extension dans le nom, celle du type reçu
 * est ajoutée. L'URL n'est révoquée qu'après coup : la révoquer aussitôt
 * annule le téléchargement dans certains navigateurs.
 */
export function saveBlob(blob: Blob, fileName: string): void {
  const extension = EXTENSIONS[blob.type]
  const name = /\.[a-z0-9]{2,5}$/i.test(fileName) || extension === undefined ? fileName : `${fileName}.${extension}`
  const url = URL.createObjectURL(blob)

  Object.assign(window.document.createElement('a'), { href: url, download: name }).click()
  window.setTimeout(() => URL.revokeObjectURL(url), 1000)
}

/** Téléchargement authentifié : un lien direct partirait sans le jeton. */
export async function downloadDocument(documentId: string, fileName: string): Promise<void> {
  try {
    saveBlob(await documentsApi.file(documentId), fileName)
  } catch (caught) {
    toast.error(errorMessage(caught))
  }
}
