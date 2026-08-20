import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable } from '@/shared/components/data/DataTable'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { Button } from '@/shared/components/ui/button'

import { useDeleteOrderLine } from '../../hooks/useOrderContent'
import type { OrderLine } from '../../types/orderDetail'
import { lineColumns } from './lineColumns'
import { OrderLineDialog } from './OrderLineDialog'
import { StatusTimelineSheet } from './StatusTimelineSheet'
import { TableToolbar } from './TableToolbar'

interface OrderLinesTabProps {
  orderId: string
  lines: OrderLine[]
  /** Le contenu est-il encore modifiable ? Le backend en décide, pas l'écran. */
  editable: boolean
}

/**
 * Lignes de la commande, en tableau.
 *
 * Les trois quantités suivies — réservée, préparée, livrée — sont en retrait :
 * elles viennent des modules Stock et Exploitation et ne se saisissent pas ici.
 * La note en pied de tableau le dit plutôt que de laisser deviner pourquoi
 * elles restent à zéro.
 */
export function OrderLinesTab({ orderId, lines, editable }: OrderLinesTabProps) {
  const { t } = useTranslation()
  const [editing, setEditing] = useState<OrderLine | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<OrderLine | null>(null)
  const [history, setHistory] = useState<OrderLine | null>(null)
  const remove = useDeleteOrderLine(orderId)

  const columns = lineColumns(t, {
    editable,
    onHistory: setHistory,
    onEdit: setEditing,
    onDelete: setDeleting,
  })

  return (
    <div className="overflow-hidden rounded-lg border bg-card">
      <TableToolbar title={t('orders.lines.title')} description={t('orders.lines.description')}>
        {editable ? (
          <PermissionGuard permission="order_lines.create">
            <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('orders.lines.add')}
            </Button>
          </PermissionGuard>
        ) : null}
      </TableToolbar>

      <DataTable
        columns={columns}
        rows={lines}
        rowKey={(row) => row.id}
        emptyMessage={t('orders.lines.title')}
      />

      <p className="border-t px-3.5 py-2.5 text-sm text-muted-foreground">
        {t('orders.lines.trackedHint')}
      </p>

      <OrderLineDialog
        key={editing?.id ?? 'new'}
        orderId={orderId}
        line={editing}
        open={editing !== null || creating}
        onOpenChange={(open) => {
          if (!open) {
            setEditing(null)
            setCreating(false)
          }
        }}
      />

      <StatusTimelineSheet
        entityType="order_line"
        entityId={history?.id ?? null}
        title={history?.name}
        subtitle={history?.articleCode ?? undefined}
        currentStatus={history?.status}
        onClose={() => setHistory(null)}
      />

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('orders.lines.remove')}
        description={t('orders.lines.deleteConfirm')}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (deleting === null) return
          remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
        }}
      />
    </div>
  )
}
