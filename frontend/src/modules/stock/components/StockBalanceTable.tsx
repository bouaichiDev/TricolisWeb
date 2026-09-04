import { useTranslation } from 'react-i18next'

import { DataTable, type Column } from '@/shared/components/data/DataTable'

import { useStockBalances } from '../hooks/useStockBalances'
import { useStockLocationOptions } from '../hooks/useStockScope'
import type { StockBalance } from '../types/stock'

/**
 * Soldes d'un article, emplacement par emplacement.
 *
 * Il n'y a **pas** de quantité « de l'article » : `stock_balances` porte
 * `unique(stock_item_id, stock_location_id)`, et la même référence peut dormir
 * dans plusieurs emplacements de plusieurs dépôts. Le total en pied est une
 * somme des lignes affichées, pas une valeur stockée — et il le reste tant que
 * la pagination tient sur une page.
 *
 * `availableQuantity` est dérivée à chaque écriture, jamais fournie : c'est ce
 * qui reste après les réservations.
 */
export function StockBalanceTable({ stockItemId }: { stockItemId: string }) {
  const { t } = useTranslation()
  const locations = useStockLocationOptions()

  const { data, isPending, error, refetch } = useStockBalances(
    { page: 1, perPage: 50, stockItemId },
    stockItemId !== '',
  )

  const rows = data?.data ?? []

  const label = (balance: StockBalance) =>
    balance.locationCode ??
    locations.options.find((option) => option.value === balance.stockLocationId)?.label ??
    balance.stockLocationId

  const columns: Column<StockBalance>[] = [
    {
      key: 'location',
      header: t('stock.fields.location'),
      cell: (row) => <span className="font-medium">{label(row)}</span>,
    },
    {
      key: 'quantity',
      header: t('stock.fields.quantity'),
      cell: (row) => String(row.quantity),
    },
    {
      key: 'reservedQuantity',
      header: t('stock.fields.reservedQuantity'),
      hideOnMobile: true,
      cell: (row) => String(row.reservedQuantity),
    },
    {
      key: 'availableQuantity',
      header: t('stock.fields.availableQuantity'),
      cell: (row) => <span className="font-medium">{String(row.availableQuantity)}</span>,
    },
  ]

  const total = rows.reduce((sum, row) => sum + Number(row.quantity), 0)
  const available = rows.reduce((sum, row) => sum + Number(row.availableQuantity), 0)

  return (
    <div className="flex flex-col gap-2">
      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(row) => row.id}
        isLoading={isPending}
        error={error}
        onRetry={() => void refetch()}
        emptyMessage={t('stock.noBalance')}
      />

      {rows.length > 0 ? (
        <p className="text-sm text-muted-foreground">
          {t('stock.totals', { total, available })}
        </p>
      ) : null}
    </div>
  )
}
