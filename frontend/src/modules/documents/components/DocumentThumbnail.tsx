import { FileAudio, FileText, File as FileIcon } from 'lucide-react'

import { previewKind, useDocumentFile } from '../hooks/useDocumentFile'
import type { Document } from '../types/document'

interface DocumentThumbnailProps {
  document: Document
  className?: string
}

/** Icône de repli, choisie sur ce que le fichier est. */
const ICONS = {
  audio: FileAudio,
  pdf: FileText,
  null: FileIcon,
} as const

/**
 * Aperçu réduit d'un document.
 *
 * Seules les images sont réellement chargées : un PDF rendu en vignette
 * coûterait un téléchargement complet pour un timbre-poste illisible, et un
 * fichier audio n'a rien à montrer. Les deux portent leur icône.
 */
export function DocumentThumbnail({ document, className }: DocumentThumbnailProps) {
  const kind = previewKind(document.mimeType)
  const file = useDocumentFile(document.id, kind === 'image')

  const box = className ?? 'size-10'

  if (kind === 'image' && file.url !== null) {
    return (
      <img
        src={file.url}
        alt={document.fileName}
        loading="lazy"
        className={`${box} shrink-0 rounded-md border object-cover`}
      />
    )
  }

  const Icon = kind === 'image' ? FileIcon : ICONS[kind ?? 'null']

  return (
    <span
      className={`${box} flex shrink-0 items-center justify-center rounded-md border bg-muted`}
    >
      <Icon className="size-4 text-muted-foreground" aria-hidden />
    </span>
  )
}
