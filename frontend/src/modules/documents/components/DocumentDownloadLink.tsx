import { Download } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { api } from '@/shared/api/client'
import { Button } from '@/shared/components/ui/button'

/**
 * Téléchargement d'un document.
 *
 * `GET /documents/{document}/download` diffuse le fichier. La route est
 * **authentifiée** : un simple `<a href>` partirait sans l'en-tête `Bearer` ni
 * `X-Organization-Id` et reviendrait en 401. Le fichier est donc récupéré par
 * le client HTTP, puis remis au navigateur par une URL d'objet.
 *
 * `storagePath` n'est jamais employé — c'est un chemin de stockage interne, que
 * la ressource n'expose d'ailleurs pas.
 */
export function DocumentDownloadLink({
  documentId,
  fileName,
}: {
  documentId: string
  fileName: string
}) {
  const { t } = useTranslation()

  return (
    <Button
      type="button"
      variant="ghost"
      size="icon"
      title={t('documents.download')}
      aria-label={t('documents.download', { name: fileName })}
      onClick={() => void download(documentId, fileName)}
    >
      <Download className="size-4" aria-hidden />
    </Button>
  )
}

/** Récupère le fichier authentifié, puis le remet au navigateur. */
async function download(documentId: string, fileName: string): Promise<void> {
  const blob = await api.blob(`/documents/${documentId}/download`)
  const url = URL.createObjectURL(blob)

  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = fileName
  anchor.click()

  // Sans cela, le blob resterait en memoire jusqu'au rechargement de la page.
  URL.revokeObjectURL(url)
}
