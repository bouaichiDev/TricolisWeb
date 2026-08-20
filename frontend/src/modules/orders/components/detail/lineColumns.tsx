import { History, Pencil, Trash2 } from 'lucide-react'
import type { TFunction } from 'i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import type { Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { cn } from '@/shared/utils/cn'

import type { OrderLine } from '../../types/orderDetail'

interface Handlers {
  editable: boolean
  onHistory: (line: OrderLine) => void
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
  { editable, onHistory, onEdit, onDelete }: Handlers,
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
      key: 'history',
      header: '',
      cell: (row) => (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="whitespace-nowrap"
          onClick={() => onHistory(row)}
        >
          <History className="size-4" aria-hidden />
          {t('orders.entityHistory.title')}
        </Button>
      ),
    },
    {
      key: 'actions',
      header: t('common.actions'),
      cell: (row) => (
        <span className="flex justify-end gap-1">
          {editable ? (
            <>
              <PermissionGuard permission="order_lines.update">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => onEdit(row)}
                  aria-label={t('orders.lines.edit')}
                >
                  <Pencil className="size-4" aria-hidden />
                </Button>
              </PermissionGuard>

              <PermissionGuard permission="order_lines.delete">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => onDelete(row)}
                  aria-label={t('orders.lines.remove')}
                >
                  <Trash2 className="size-4" aria-hidden />
                </Button>
              </PermissionGuard>
            </>
          ) : null}
        </span>
      ),
    },
  ]
}
