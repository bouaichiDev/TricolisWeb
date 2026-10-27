import { Eye } from 'lucide-react'
import type { TFunction } from 'i18next'
import { Link } from 'react-router-dom'

import type { Column } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDateTime } from '@/shared/utils/format'

import { AdjustmentDifference } from './AdjustmentDifference'
import { formatAdjustmentLocation, type StockAdjustmentRequest } from '../../types/stockAdjustment'
import { formatStockQuantity, STOCK_ADJUSTMENT_REQUEST_SOURCE } from '../../utils/stockSources'

/**
 * Colonnes d'une liste de demandes de correction, partagées par la liste du
 * stock et l'onglet de la fiche client — qui omet la colonne client.
 */
export function adjustmentColumns(
  t: TFunction,
  { withCustomer = true }: { withCustomer?: boolean } = {},
): Column<StockAdjustmentRequest>[] {
  const columns: Column<StockAdjustmentRequest>[] = [
    {
      key: 'createdAt',
      header: t('stock.adjustments.fields.createdAt'),
      sortKey: 'created_at',
      cell: (row) => formatDateTime(row.createdAt),
    },
    {
      key: 'customer',
      header: t('stock.fields.customer'),
      cell: (row) => row.customer?.name ?? row.customerId,
    },
    {
      key: 'article',
      header: t('stock.fields.articleCode'),
      cell: (row) => (
        <Link to={`/stock/items/${row.stockItemId}`} className="font-medium hover:underline">
          {row.stockItem?.articleCode ?? row.stockItemId}
        </Link>
      ),
    },
    {
      key: 'location',
      header: t('stock.fields.location'),
      hideOnMobile: true,
      cell: (row) =>
        formatAdjustmentLocation(row.location) ?? (
          <span className="text-muted-foreground">{t('stock.adjustments.wholeItem')}</span>
        ),
    },
    {
      key: 'displayedQuantity',
      header: t('stock.adjustments.fields.displayedShort'),
      hideOnMobile: true,
      cell: (row) => formatStockQuantity(row.displayedQuantity),
    },
    {
      key: 'requestedQuantity',
      header: t('stock.adjustments.fields.requestedShort'),
      sortKey: 'requested_quantity',
      cell: (row) => formatStockQuantity(row.requestedQuantity),
    },
    {
      key: 'difference',
      header: t('stock.adjustments.fields.difference'),
      cell: (row) => <AdjustmentDifference value={row.difference} />,
    },
    {
      key: 'status',
      header: t('stock.fields.status'),
      sortKey: 'status',
      cell: (row) => <StatusBadge status={row.status} source={STOCK_ADJUSTMENT_REQUEST_SOURCE} />,
    },
    {
      key: 'requester',
      header: t('stock.adjustments.fields.requester'),
      hideOnMobile: true,
      cell: (row) => row.requester?.name ?? '—',
    },
  ]

  return withCustomer ? columns : columns.filter((column) => column.key !== 'customer')
}

export function adjustmentRowActions(t: TFunction) {
  return (row: StockAdjustmentRequest) => (
    <RowActions
      actions={[
        {
          key: 'view',
          icon: Eye,
          label: t('common.view'),
          to: `/stock/adjustment-requests/${row.id}`,
        },
      ]}
    />
  )
}
