import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { formatSize } from '@/shared/utils/format'

import type { OrderDocument } from '../types'
import { DownloadButton } from './DocumentParts'
import { previewKind, useOrderDocumentFile } from './useOrderDocumentFile'

interface Props {
  orderId?: string
  document: OrderDocument | null
  onClose: () => void
}

/** Aperçu d'un document : image ou PDF dans la fenêtre, téléchargement sinon. */
export function DocumentPreviewDialog({ orderId, document, onClose }: Props) {
  return (
    <Dialog open={document !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle className="truncate">{document?.fileName ?? ''}</DialogTitle>
          <DialogDescription>{document ? `${document.mimeType} · ${formatSize(document.size)}` : ''}</DialogDescription>
        </DialogHeader>
        {document && <Preview orderId={orderId} document={document} />}
        {document && (
          <span className="flex items-center gap-2">
            <DownloadButton orderId={orderId} document={document} />
            <span className="text-muted-foreground text-sm">Télécharger</span>
          </span>
        )}
      </DialogContent>
    </Dialog>
  )
}

function Preview({ orderId, document }: { orderId?: string; document: OrderDocument }) {
  const kind = previewKind(document.mimeType)
  const file = useOrderDocumentFile(orderId, document.id, kind !== null)
  const note = (text: string) => <p className="text-muted-foreground text-sm">{text}</p>

  if (kind === null) return note('Pas d’aperçu pour ce format : téléchargez le fichier.')
  if (file.isError) return note('L’aperçu n’a pas pu être chargé.')
  if (file.url === null) return note('Chargement…')

  if (kind === 'image') {
    return <img src={file.url} alt={document.fileName} className="max-h-[70vh] w-full rounded-md border object-contain" />
  }

  return (
    <object data={file.url} type="application/pdf" className="h-[70vh] w-full rounded-md border">
      {note('Pas d’aperçu pour ce format : téléchargez le fichier.')}
    </object>
  )
}
