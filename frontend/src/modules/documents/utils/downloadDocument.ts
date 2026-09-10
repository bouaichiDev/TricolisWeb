import { api } from '@/shared/api/client'

/**
 * Récupère le fichier d'un document, puis le remet au navigateur.
 *
 * `GET /documents/{document}/download` est **authentifié** : un simple
 * `<a href>` partirait sans l'en-tête `Bearer` ni `X-Organization-Id` et
 * reviendrait en 401. Le fichier passe donc par le client HTTP, puis par une
 * URL d'objet.
 *
 * Écrit ici plutôt que dans le composant qui l'a inauguré : le bon de livraison
 * télécharge le sien juste après l'avoir généré, sans bouton de liste à
 * cliquer, et recopier ces huit lignes garantissait qu'une des deux copies
 * oublie de libérer le blob.
 */
export async function downloadDocument(documentId: string, fileName: string): Promise<void> {
  const blob = await api.blob(`/documents/${documentId}/download`)
  const url = URL.createObjectURL(blob)

  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = fileName
  anchor.click()

  // Sans cela, le blob resterait en memoire jusqu'au rechargement de la page.
  URL.revokeObjectURL(url)
}
