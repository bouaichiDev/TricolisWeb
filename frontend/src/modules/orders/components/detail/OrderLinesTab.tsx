import { useTranslation } from 'react-i18next'

import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'

import type { OrderLine } from '../../types/orderDetail'

const show = (value: number | string | null): string => (value === null ? '—' : String(value))

/**
 * Lignes de la commande.
 *
 * `fromCatalog` est calculé par la ressource : il dit si la ligne provient d'un
 * article de catalogue ou d'une saisie libre. Les deux coexistent dans une même
 * commande.
 */
export function OrderLinesTab({ lines }: { lines: OrderLine[] }) {
  const { t } = useTranslation()

  const columns: Column<OrderLine>[] = [
    {
      key: 'name',
      header: t('orders.fields.name'),
      cell: (row) => (
        <span className="flex flex-col">
          <span className="font-medium">{row.name}</span>
          {row.articleCode !== null ? (
            <span className="text-xs text-muted-foreground">{row.articleCode}</span>
          ) : null}
        </span>
      ),
    },
    {
      key: 'origin',
      header: t('orders.lines.origin'),
      hideOnMobile: true,
      cell: (row) =>
        row.fromCatalog ? (
          <Badge variant="secondary">{t('orders.lines.catalogItem')}</Badge>
        ) : (
          <Badge variant="outline">{t('orders.lines.manualEntry')}</Badge>
        ),
    },
    { key: 'quantity', header: t('orders.fields.quantity'), cell: (row) => show(row.quantity) },
    {
      key: 'weight',
      header: t('orders.fields.weight'),
      hideOnMobile: true,
      cell: (row) => show(row.weight),
    },
    {
      key: 'volume',
      header: t('orders.fields.volume'),
      hideOnMobile: true,
      cell: (row) => show(row.volume),
    },
    {
      key: 'barcode',
      header: t('orders.fields.barcode'),
      hideOnMobile: true,
      cell: (row) => row.barcode ?? '—',
    },
  ]

  return (
    <SectionCard title={t('orders.lines.title')}>
      <DataTable
        columns={columns}
        rows={lines}
        rowKey={(row) => row.id}
        emptyMessage={t('orders.lines.title')}
      />
    </SectionCard>
  )
}
