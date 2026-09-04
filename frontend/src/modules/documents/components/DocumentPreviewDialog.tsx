import { useTranslation } from 'react-i18next'

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { formatBytes } from '@/shared/utils/format'

import { DocumentDownloadLink } from './DocumentDownloadLink'
import { previewKind, useDocumentFile } from '../hooks/useDocumentFile'
import type { Document } from '../types/document'

interface DocumentPreviewDialogProps {
  document: Document | null
  onClose: () => void
}

/**
 * Aperçu d'un document, en grand.
 *
 * Photo, vocal et PDF s'ouvrent sur place : une réclamation se juge sur la
 * rayure qu'on voit, pas sur un nom de fichier. Le reste garde son bouton de
 * téléchargement, seul moyen honnête d'ouvrir un format que le navigateur ne
 * sait pas rendre.
 */
export function DocumentPreviewDialog({ document, onClose }: DocumentPreviewDialogProps) {
  const { t } = useTranslation()

  return (
    <Dialog open={document !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle className="truncate">{document?.fileName ?? ''}</DialogTitle>
          <DialogDescription>
            {document ? `${document.mimeType} · ${formatBytes(document.size)}` : ''}
          </DialogDescription>
        </DialogHeader>

        {document ? <Preview document={document} /> : null}

        {document ? (
          <span className="flex items-center gap-2">
            <DocumentDownloadLink documentId={document.id} fileName={document.fileName} />
            <span className="text-sm text-muted-foreground">{t('documents.download')}</span>
          </span>
        ) : null}
      </DialogContent>
    </Dialog>
  )
}

function Preview({ document }: { document: Document }) {
  const { t } = useTranslation()
  const kind = previewKind(document.mimeType)
  const file = useDocumentFile(document.id, kind !== null)

  if (kind === null) return <p className="text-sm text-muted-foreground">{t('documents.noPreview')}</p>

  if (file.isError) return <p className="text-sm text-muted-foreground">{t('documents.previewFailed')}</p>

  if (file.url === null) return <p className="text-sm text-muted-foreground">{t('common.loading')}</p>

  if (kind === 'image') {
    return (
      <img
        src={file.url}
        alt={document.fileName}
        className="max-h-[70vh] w-full rounded-md border object-contain"
      />
    )
  }

  if (kind === 'audio') {
    // Les controles natifs suffisent, et ils sont accessibles au clavier.
    return <audio src={file.url} controls className="w-full" aria-label={document.fileName} />
  }

  return (
    <object data={file.url} type="application/pdf" className="h-[70vh] w-full rounded-md border">
      <p className="p-4 text-sm text-muted-foreground">{t('documents.noPreview')}</p>
    </object>
  )
}
