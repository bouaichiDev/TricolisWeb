import { Eye, MessageSquareWarning, Pencil } from 'lucide-react'
import { useState } from 'react'

import { paths } from '@/app/router/paths'
import { RowActions, type RowAction } from '@/shared/components/data/RowActions'

import type { StockItem } from '../types'
import { AdjustmentRequestDialog } from './AdjustmentRequestDialog'
import { StockItemDialog } from './StockItemDialog'

/**
 * Les actions d'un article de stock dans la liste : voir, modifier ses
 * informations, demander une correction — selon `item.actions`, calculé par
 * le backend. Jamais de modification de quantité.
 */
export function StockRowActions({ item }: { item: StockItem }) {
  const [editing, setEditing] = useState(false)
  const [adjusting, setAdjusting] = useState(false)

  const actions: RowAction[] = [
    { key: 'view', icon: Eye, label: 'Consulter', to: paths.stockItem(item.id) },
    ...(item.actions.canEditMetadata ? [{ key: 'edit', icon: Pencil, label: 'Modifier', onClick: () => setEditing(true) }] : []),
    ...(item.actions.canRequestChange ? [{ key: 'adjust', icon: MessageSquareWarning, label: 'Demander une correction', onClick: () => setAdjusting(true) }] : []),
  ]

  return (
    <>
      <RowActions actions={actions} />
      {editing && <StockItemDialog item={item} open onOpenChange={setEditing} />}
      {adjusting && <AdjustmentRequestDialog item={item} open onOpenChange={setAdjusting} />}
    </>
  )
}
