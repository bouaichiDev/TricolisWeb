import { LayoutGrid, List } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

import type { PaginationMeta } from '@/shared/api/types'
import { DataTablePagination } from '@/shared/components/data/DataTablePagination'
import { Button } from '@/shared/components/ui/button'

import { DocumentCard } from './DocumentCard'
import { DocumentPreviewDialog } from './DocumentPreviewDialog'
import { DocumentRow } from './DocumentRow'
import type { Document } from '../types/document'

type View = 'list' | 'cards'

interface DocumentGalleryProps {
  documents: Document[]
  /** Vue initiale ; les photos se regardent mieux en vignettes. */
  defaultView?: View
  /**
   * Ligne secondaire propre au contexte : type et statut dans une commande,
   * date de livraison pour une preuve. Le composant ne les devine pas.
   */
  details?: (document: Document) => ReactNode
  isLoading?: boolean
  emptyMessage?: string
  meta?: PaginationMeta
  onPageChange?: (page: number) => void
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
export function DocumentGallery({
  documents,
  defaultView = 'list',
  details,
  isLoading = false,
  emptyMessage,
  meta,
  onPageChange,
}: DocumentGalleryProps) {
  const { t } = useTranslation()
  const [view, setView] = useState<View>(defaultView)
  const [opened, setOpened] = useState<Document | null>(null)

  if (isLoading) return <p className="text-sm text-muted-foreground">{t('common.loading')}</p>

  if (documents.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">{emptyMessage ?? t('documents.empty')}</p>
    )
  }

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
            <DocumentRow
              key={item.id}
              document={item}
              details={details?.(item)}
              onOpen={() => setOpened(item)}
            />
          ))}
        </ul>
      ) : (
        <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
          {documents.map((item) => (
            <DocumentCard
              key={item.id}
              document={item}
              details={details?.(item)}
              onOpen={() => setOpened(item)}
            />
          ))}
        </ul>
      )}

      {meta && meta.total > 0 ? (
        <DataTablePagination meta={meta} onPageChange={onPageChange} />
      ) : null}

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
  children: ReactNode
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
