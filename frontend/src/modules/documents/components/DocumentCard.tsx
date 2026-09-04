import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

import { formatBytes } from '@/shared/utils/format'

import { DocumentDownloadLink } from './DocumentDownloadLink'
import { DocumentThumbnail } from './DocumentThumbnail'
import type { Document } from '../types/document'

interface DocumentCardProps {
  document: Document
  details?: ReactNode
  onOpen: () => void
}

/** Une vignette : pour reconnaître une photo qu'aucun nom ne décrit. */
export function DocumentCard({ document, details, onOpen }: DocumentCardProps) {
  const { t } = useTranslation()

  return (
    <li className="flex flex-col gap-1 rounded-md border p-2">
      <button
        type="button"
        className="flex flex-col gap-1 text-left"
        onClick={onOpen}
        title={t('documents.preview')}
      >
        <DocumentThumbnail document={document} className="aspect-square w-full" />
        <span className="truncate text-sm">{document.fileName}</span>
      </button>

      <span className="flex items-center justify-between gap-1">
        <span className="min-w-0 text-xs text-muted-foreground">
          <span className="block truncate">{formatBytes(document.size)}</span>
          {details === undefined ? null : <span className="block truncate">{details}</span>}
        </span>

        <DocumentDownloadLink documentId={document.id} fileName={document.fileName} />
      </span>
    </li>
  )
}
