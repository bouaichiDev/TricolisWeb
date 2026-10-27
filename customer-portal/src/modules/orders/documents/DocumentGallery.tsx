import { LayoutGrid, List } from 'lucide-react'
import { useState, type ReactNode } from 'react'

import { Button } from '@/shared/components/ui/button'

import type { OrderDocument } from '../types'
import { DocumentCard, DocumentRow } from './DocumentParts'
import { DocumentPreviewDialog } from './DocumentPreviewDialog'

type View = 'list' | 'cards'

const STORAGE_KEY = 'tricolis.customer-portal.documents-view'

function storedView(): View {
  try {
    return localStorage.getItem(STORAGE_KEY) === 'cards' ? 'cards' : 'list'
  } catch {
    return 'list'
  }
}

/**
 * Les documents d'une commande, en liste ou en vignettes — la galerie du
 * back-office. Le choix de vue est retenu d'une visite à l'autre ; un clic
 * ouvre l'aperçu.
 */
export function DocumentGallery({ orderId, documents }: { orderId?: string; documents: OrderDocument[] }) {
  const [view, setView] = useState<View>(storedView)
  const [opened, setOpened] = useState<OrderDocument | null>(null)

  const choose = (next: View) => {
    setView(next)
    try {
      localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // Stockage indisponible : le choix vaut pour la visite.
    }
  }

  return (
    <div className="flex flex-col gap-2">
      <span className="flex justify-end gap-1">
        <ViewButton active={view === 'list'} label="Vue liste" onClick={() => choose('list')}>
          <List className="size-4" aria-hidden />
        </ViewButton>
        <ViewButton active={view === 'cards'} label="Vue vignettes" onClick={() => choose('cards')}>
          <LayoutGrid className="size-4" aria-hidden />
        </ViewButton>
      </span>

      {view === 'list' ? (
        <ul className="flex flex-col gap-1">
          {documents.map((item) => (
            <DocumentRow key={item.id} orderId={orderId} document={item} onOpen={() => setOpened(item)} />
          ))}
        </ul>
      ) : (
        <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
          {documents.map((item) => (
            <DocumentCard key={item.id} orderId={orderId} document={item} onOpen={() => setOpened(item)} />
          ))}
        </ul>
      )}

      <DocumentPreviewDialog orderId={orderId} document={opened} onClose={() => setOpened(null)} />
    </div>
  )
}

function ViewButton({ active, label, onClick, children }: { active: boolean; label: string; onClick: () => void; children: ReactNode }) {
  return (
    <Button type="button" variant={active ? 'secondary' : 'ghost'} size="icon" title={label} aria-label={label} aria-pressed={active} onClick={onClick}>
      {children}
    </Button>
  )
}
