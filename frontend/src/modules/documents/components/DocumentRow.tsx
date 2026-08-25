import type { ReactNode } from 'react'

import { formatBytes } from '@/shared/utils/format'

import { DocumentDownloadLink } from './DocumentDownloadLink'
import { DocumentThumbnail } from './DocumentThumbnail'
import type { Document } from '../types/document'

interface DocumentRowProps {
  document: Document
  details?: ReactNode
  onOpen: () => void
}

/** Une ligne : pour retrouver un fichier par son nom. */
export function DocumentRow({ document, details, onOpen }: DocumentRowProps) {
  return (
    <li className="flex items-center gap-2 rounded-md border px-2 py-1.5">
      <DocumentThumbnail document={document} />

      <button type="button" className="min-w-0 flex-1 text-left" onClick={onOpen}>
        <span className="block truncate text-sm underline-offset-2 hover:underline">
          {document.fileName}
        </span>
        <span className="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
          <span>
            {document.mimeType} · {formatBytes(document.size)}
          </span>
          {details}
        </span>
      </button>

      <DocumentDownloadLink documentId={document.id} fileName={document.fileName} />
    </li>
  )
}
