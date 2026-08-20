import { History, Pencil, RefreshCw, Trash2 } from 'lucide-react'
import type { TFunction } from 'i18next'

import type { Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Badge } from '@/shared/components/ui/badge'
import { cn } from '@/shared/utils/cn'

import type { OrderLine } from '../../types/orderDetail'
import { RowActions } from './RowActions'

interface Handlers {
  editable: boolean
  onHistory: (line: OrderLine) => void
  onStatus: (line: OrderLine) => void
  onEdit: (line: OrderLine) => void
  onDelete: (line: OrderLine) => void
}

const num = (value: number | string | null): string => (value === null ? '—' : String(value))

const right = (value: string, muted = false) => (
  <span className={cn('block text-right font-mono', muted && 'text-muted-foreground')}>{value}</span>
)

/**
 * Colonnes du tableau des lignes.
 *
 * Les trois quantités suivies — réservée, préparée, livrée — sont en gris : ce
 * sont des valeurs lues, jamais saisies ici. Elles viennent des modules Stock
 * et Exploitation, et le pied de tableau le rappelle.
 */
export function lineColumns(
  t: TFunction,
  { editable, onHistory, onStatus, onEdit, onDelete }: Handlers,
): Column<OrderLine>[] {
  return [
    {
      key: 'name',
      header: t('orders.fields.name'),
      cell: (row) => (
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <span className="font-semibold">{row.name}</span>
            <Badge variant={row.fromCatalog ? 'secondary' : 'outline'}>
              {row.fromCatalog ? t('orders.lines.catalogItem') : t('orders.lines.manualEntry')}
            </Badge>
          </div>
          {row.description !== null ? (
            <p className="text-sm text-muted-foreground">{row.description}</p>
          ) : null}
        </div>
      ),
    },
    { key: 'code', header: t('orders.fields.articleCode'), cell: (row) => row.articleCode ?? '—' },
    { key: 'barcode', header: t('orders.fields.barcode'), cell: (row) => row.barcode ?? '—' },
    { key: 'qty', header: t('orders.fields.quantity'), cell: (row) => right(num(row.quantity)) },
    {
      key: 'reserved',
      header: t('orders.fields.reservedQuantity'),
      hideOnMobile: true,
      cell: (row) => right(num(row.reservedQuantity), true),
    },
    {
      key: 'prepared',
      header: t('orders.fields.preparedQuantity'),
      hideOnMobile: true,
      cell: (row) => right(num(row.preparedQuantity), true),
    },
    {
      key: 'delivered',
      header: t('orders.fields.deliveredQuantity'),
      hideOnMobile: true,
      cell: (row) => right(num(row.deliveredQuantity), true),
    },
    { key: 'weight', header: t('orders.fields.weight'), cell: (row) => right(num(row.weight)) },
    { key: 'volume', header: t('orders.fields.volume'), cell: (row) => right(num(row.volume)) },
    {
      key: 'status',
      header: t('orders.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: 'actions',
      header: t('common.actions'),
      cell: (row) => (
        <RowActions
          actions={[
            {
              key: 'history',
              label: t('orders.entityHistory.title'),
              icon: History,
              onSelect: () => onHistory(row),
            },
            // Statut, modification et suppression disparaissent avec le contenu
            // figé : le serveur les refuserait.
            ...(editable
              ? [
                  {
                    key: 'status',
                    label: t('orders.lines.changeStatus'),
                    icon: RefreshCw,
                    permission: 'order_lines.update',
                    onSelect: () => onStatus(row),
                  },
                  {
                    key: 'edit',
                    label: t('orders.lines.edit'),
                    icon: Pencil,
                    permission: 'order_lines.update',
                    onSelect: () => onEdit(row),
                  },
                  {
                    key: 'delete',
                    label: t('orders.lines.remove'),
                    icon: Trash2,
                    permission: 'order_lines.delete',
                    destructive: true,
                    onSelect: () => onDelete(row),
                  },
                ]
              : []),
          ]}
        />
      ),
    },
  ]
}
