import { LayoutGrid, List } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import { formatBytes } from '@/shared/utils/format'

import { DocumentDownloadLink } from './DocumentDownloadLink'
import { DocumentPreviewDialog } from './DocumentPreviewDialog'
import { DocumentThumbnail } from './DocumentThumbnail'
import type { Document } from '../types/document'

type View = 'list' | 'cards'

interface DocumentGalleryProps {
  documents: Document[]
  /** Vue initiale ; les photos se regardent mieux en vignettes. */
  defaultView?: View
}

/**
 * Une liste de documents, en lignes ou en vignettes.
 *
 * Les deux vues montrent la même chose et servent deux lectures : la liste
 * pour retrouver un fichier par son nom, les vignettes pour reconnaître une
 * photo qu'on n'aurait jamais retrouvée par son nom d'appareil.
 *
 * Ouvrir un document l'affiche sur place — image, vocal ou PDF.
 */
export function DocumentGallery({ documents, defaultView = 'list' }: DocumentGalleryProps) {
  const { t } = useTranslation()
  const [view, setView] = useState<View>(defaultView)
  const [opened, setOpened] = useState<Document | null>(null)

  return (
    <div className="flex flex-col gap-2">
      <span className="flex justify-end gap-1">
        <ViewButton
          active={view === 'list'}
          label={t('documents.viewList')}
          onClick={() => setView('list')}
        >
          <List className="size-4" aria-hidden />
        </ViewButton>
        <ViewButton
          active={view === 'cards'}
          label={t('documents.viewCards')}
          onClick={() => setView('cards')}
        >
          <LayoutGrid className="size-4" aria-hidden />
        </ViewButton>
      </span>

      {view === 'list' ? (
        <ul className="flex flex-col gap-1">
          {documents.map((item) => (
            <li key={item.id} className="flex items-center gap-2 rounded-md border px-2 py-1.5">
              <DocumentThumbnail document={item} />

              <button
                type="button"
                className="min-w-0 flex-1 text-left"
                onClick={() => setOpened(item)}
              >
                <span className="block truncate text-sm underline-offset-2 hover:underline">
                  {item.fileName}
                </span>
                <span className="block text-xs text-muted-foreground">
                  {item.mimeType} · {formatBytes(item.size)}
                </span>
              </button>

              <DocumentDownloadLink documentId={item.id} fileName={item.fileName} />
            </li>
          ))}
        </ul>
      ) : (
        <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
          {documents.map((item) => (
            <li key={item.id} className="flex flex-col gap-1 rounded-md border p-2">
              <button
                type="button"
                className="flex flex-col gap-1 text-left"
                onClick={() => setOpened(item)}
                title={t('documents.preview')}
              >
                <DocumentThumbnail document={item} className="aspect-square w-full" />
                <span className="truncate text-sm">{item.fileName}</span>
              </button>

              <span className="flex items-center justify-between gap-1">
                <span className="truncate text-xs text-muted-foreground">
                  {formatBytes(item.size)}
                </span>
                <DocumentDownloadLink documentId={item.id} fileName={item.fileName} />
              </span>
            </li>
          ))}
        </ul>
      )}

      <DocumentPreviewDialog document={opened} onClose={() => setOpened(null)} />
    </div>
  )
}

function ViewButton({
  active,
  label,
  onClick,
  children,
}: {
  active: boolean
  label: string
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <Button
      type="button"
      variant={active ? 'secondary' : 'ghost'}
      size="icon"
      title={label}
      aria-label={label}
      aria-pressed={active}
      onClick={onClick}
    >
      {children}
    </Button>
  )
}
