import { Download } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'

import { downloadDocument } from '../utils/downloadDocument'

/**
 * Téléchargement d'un document.
 *
 * `GET /documents/{document}/download` diffuse le fichier. La route est
 * **authentifiée** : un simple `<a href>` partirait sans l'en-tête `Bearer` ni
 * `X-Organization-Id` et reviendrait en 401. Le fichier est donc récupéré par
 * le client HTTP, puis remis au navigateur par une URL d'objet — c'est ce que
 * fait `downloadDocument`, partagé avec la génération du bon de livraison.
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
      onClick={() => void downloadDocument(documentId, fileName)}
    >
      <Download className="size-4" aria-hidden />
    </Button>
  )
}
