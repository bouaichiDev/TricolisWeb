import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { formatDateTime } from '@/shared/utils/format'

import { useStockBalances } from '../hooks/useStockBalances'
import type { StockBalance } from '../types/stock'
import { formatStockQuantity } from '../utils/stockSources'

/**
 * Ce qui dort dans un emplacement.
 *
 * L'inverse de `StockBalanceTable`, qui montre où dort un article. Le même
 * `stock_balances` répond aux deux questions parce qu'il porte
 * `unique(stock_item_id, stock_location_id)` : une ligne par couple.
 *
 * Lecture seule, sans exception. Une quantité qui doit changer passe par un
 * mouvement — c'est la seule écriture que le serveur expose.
 */
export function LocationBalancesTable({ stockLocationId }: { stockLocationId: string }) {
  const { t } = useTranslation()

  const { data, isPending, error, refetch } = useStockBalances(
    { page: 1, perPage: 50, stockLocationId, sort: 'updated_at', direction: 'desc' },
    stockLocationId !== '',
  )

  const columns: Column<StockBalance>[] = [
    {
      key: 'article',
      header: t('stock.fields.articleCode'),
      cell: (row) => (
        <Link to={`/stock/items/${row.stockItemId}`} className="font-medium hover:underline">
          {row.articleCode ?? row.stockItemId}
        </Link>
      ),
    },
    {
      key: 'quantity',
      header: t('stock.fields.quantity'),
      cell: (row) => formatStockQuantity(row.quantity),
    },
    {
      key: 'reservedQuantity',
      header: t('stock.fields.reservedQuantity'),
      hideOnMobile: true,
      cell: (row) => formatStockQuantity(row.reservedQuantity),
    },
    {
      key: 'availableQuantity',
      header: t('stock.fields.availableQuantity'),
      cell: (row) => (
        <span className="font-medium">{formatStockQuantity(row.availableQuantity)}</span>
      ),
    },
    {
      key: 'updatedAt',
      header: t('stock.fields.updatedAt'),
      hideOnMobile: true,
      cell: (row) => formatDateTime(row.updatedAt),
    },
  ]

  return (
    <DataTable
      columns={columns}
      rows={data?.data ?? []}
      rowKey={(row) => row.id}
      isLoading={isPending}
      error={error}
      onRetry={() => void refetch()}
      emptyMessage={t('stock.noBalanceHere')}
    />
  )
}
