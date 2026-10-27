import { Download, File as FileIcon, FileText } from 'lucide-react'

import { Button } from '@/shared/components/ui/button'
import { cn } from '@/shared/utils/cn'
import { formatDate, formatSize } from '@/shared/utils/format'

import type { OrderDocument } from '../types'
import { downloadOrderDocument } from './downloadOrderDocument'
import { previewKind, useOrderDocumentFile } from './useOrderDocumentFile'

interface PartProps {
  orderId?: string
  document: OrderDocument
}

/** Vignette : l'image elle-même, une icône pour le reste — comme au back-office. */
export function DocumentThumbnail({ orderId, document, className }: PartProps & { className?: string }) {
  const kind = previewKind(document.mimeType)
  const file = useOrderDocumentFile(orderId, document.id, kind === 'image')
  const box = className ?? 'size-10'

  if (kind === 'image' && file.url !== null) {
    return <img src={file.url} alt={document.fileName} loading="lazy" className={cn(box, 'shrink-0 rounded-md border object-cover')} />
  }

  const Icon = kind === 'pdf' ? FileText : FileIcon

  return (
    <span className={cn(box, 'bg-muted flex shrink-0 items-center justify-center rounded-md border')}>
      <Icon className="text-muted-foreground size-4" aria-hidden />
    </span>
  )
}

export function DownloadButton({ orderId, document }: PartProps) {
  return (
    <Button
      type="button"
      variant="ghost"
      size="icon"
      title="Télécharger"
      aria-label={`Télécharger ${document.fileName}`}
      onClick={() => void downloadOrderDocument(orderId, document)}
    >
      <Download className="size-4" aria-hidden />
    </Button>
  )
}

/** Une ligne de la vue liste. */
export function DocumentRow({ orderId, document, onOpen }: PartProps & { onOpen: () => void }) {
  return (
    <li className="flex items-center gap-2 rounded-md border px-2 py-1.5">
      <DocumentThumbnail orderId={orderId} document={document} />
      <button type="button" className="min-w-0 flex-1 text-left" onClick={onOpen}>
        <span className="block truncate text-sm underline-offset-2 hover:underline">{document.fileName}</span>
        <span className="text-muted-foreground flex flex-wrap items-center gap-x-2 text-xs">
          <span>
            {document.mimeType} · {formatSize(document.size)}
          </span>
          <span>{formatDate(document.createdAt)}</span>
        </span>
      </button>
      <DownloadButton orderId={orderId} document={document} />
    </li>
  )
}

/** Une carte de la vue en vignettes. */
export function DocumentCard({ orderId, document, onOpen }: PartProps & { onOpen: () => void }) {
  return (
    <li className="flex flex-col gap-1 rounded-md border p-2">
      <button type="button" className="flex flex-col gap-1 text-left" onClick={onOpen} title="Aperçu">
        <DocumentThumbnail orderId={orderId} document={document} className="aspect-square w-full" />
        <span className="truncate text-sm">{document.fileName}</span>
      </button>
      <span className="flex items-center justify-between gap-1">
        <span className="text-muted-foreground min-w-0 text-xs">
          <span className="block truncate">{formatSize(document.size)}</span>
          <span className="block truncate">{formatDate(document.createdAt)}</span>
        </span>
        <DownloadButton orderId={orderId} document={document} />
      </span>
    </li>
  )
}
