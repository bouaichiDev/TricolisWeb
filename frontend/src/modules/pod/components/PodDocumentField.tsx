import { FileImage, PenLine } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import type { Document } from '@/modules/documents/types/document'
import { formatBytes } from '@/shared/utils/format'

interface PodDocumentFieldProps {
  kind: 'signature' | 'photo'
  documentId: string | null
  document?: Document
}

/**
 * Signature ou photo d'une preuve de livraison.
 *
 * Le §11 demande de réutiliser le module Documents pour la prévisualisation et
 * le téléchargement. La Phase 2 avait établi qu'**aucune route de
 * téléchargement n'existe** : `documents` expose la métadonnée, pas le fichier.
 *
 * Le champ montre donc ce que le serveur donne — nom, type, taille — et le dit.
 * Fabriquer un lien vers un `storagePath` serait doublement fautif : le §11
 * l'interdit, et le chemin n'est pas une adresse servie.
 */
export function PodDocumentField({ kind, documentId, document }: PodDocumentFieldProps) {
  const { t } = useTranslation()
  const Icon = kind === 'signature' ? PenLine : FileImage

  return (
    <div>
      <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        {t(`pod.fields.${kind}`)}
      </p>

      {documentId === null ? (
        <p className="mt-1 text-sm text-muted-foreground">{t('pod.noDocument')}</p>
      ) : document ? (
        <p className="mt-1 flex items-center gap-2 text-sm">
          <Icon className="size-4 shrink-0 text-muted-foreground" aria-hidden />
          <span className="min-w-0">
            <span className="block truncate">{document.fileName}</span>
            <span className="block text-xs text-muted-foreground">
              {document.mimeType} · {formatBytes(document.size)}
            </span>
          </span>
        </p>
      ) : (
        <p className="mt-1 font-mono text-xs text-muted-foreground">{documentId}</p>
      )}
    </div>
  )
}
