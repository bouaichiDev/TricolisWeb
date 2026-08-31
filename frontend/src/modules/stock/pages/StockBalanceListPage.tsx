import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { ControlledCheckbox } from '@/shared/components/form/ControlledCheckbox'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { formatDateTime } from '@/shared/utils/format'

import { CustomerFilterSelect } from '../components/CustomerFilterSelect'
import { useStockBalances } from '../hooks/useStockBalances'
import type { StockBalance } from '../types/stock'
import type { StockBalanceFilters } from '../types/stockFilters'
import { formatStockQuantity } from '../utils/stockSources'

/**
 * Soldes de stock — **lecture seule**.
 *
 * Aucun bouton de création, de modification ni de suppression, et ce n'est pas
 * une restriction d'interface : `StockBalancePolicy` n'expose que `viewAny` et
 * `view`, et aucune route n'écrit un solde. Une quantité se déplace par un
 * mouvement, se réserve par une réservation ; le solde en est la conséquence.
 *
 * Pas de champ de recherche non plus : `StockBalanceListQuery` n'en applique
 * aucune. Un `search=` serait accepté par `ListRequest` puis ignoré — l'écran
 * paraîtrait chercher sans le faire. Les filtres réels sont le client,
 * l'article, l'emplacement, et « disponible seulement ».
 */
export function StockBalanceListPage() {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<StockBalanceFilters>({
    page: 1,
    perPage: 25,
    sort: 'updated_at',
    direction: 'desc',
  })

  const { data, isPending, error, refetch } = useStockBalances(filters)

  const patch = (next: Partial<StockBalanceFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

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
      key: 'location',
      header: t('stock.fields.location'),
      cell: (row) => (
        <Link to={`/stock/locations/${row.stockLocationId}`} className="hover:underline">
          {row.locationCode ?? row.stockLocationId}
        </Link>
      ),
    },
    {
      key: 'quantity',
      header: t('stock.fields.quantity'),
      sortKey: 'quantity',
      cell: (row) => formatStockQuantity(row.quantity),
    },
    {
      key: 'reservedQuantity',
      header: t('stock.fields.reservedQuantity'),
      sortKey: 'reserved_quantity',
      hideOnMobile: true,
      cell: (row) => formatStockQuantity(row.reservedQuantity),
    },
    {
      key: 'availableQuantity',
      header: t('stock.fields.availableQuantity'),
      sortKey: 'available_quantity',
      cell: (row) => (
        <span className="font-medium">{formatStockQuantity(row.availableQuantity)}</span>
      ),
    },
    {
      key: 'updatedAt',
      header: t('stock.fields.updatedAt'),
      sortKey: 'updated_at',
      hideOnMobile: true,
      cell: (row) => formatDateTime(row.updatedAt),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('stock.balancesTitle')} description={t('stock.balancesSubtitle')} />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <CustomerFilterSelect
          value={filters.customerId}
          onChange={(customerId) => patch({ customerId })}
        />
        <ControlledCheckbox
          label={t('stock.availableOnly')}
          checked={filters.availableOnly ?? false}
          onChange={(checked) => patch({ availableOnly: checked ? true : undefined })}
        />
      </div>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        sort={filters.sort}
        direction={filters.direction}
        onSortChange={(sortKey) =>
          setFilters((current) => ({
            ...current,
            sort: sortKey,
            direction: current.sort === sortKey && current.direction === 'desc' ? 'asc' : 'desc',
          }))
        }
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        emptyMessage={t('stock.noBalance')}
      />
    </div>
  )
}
