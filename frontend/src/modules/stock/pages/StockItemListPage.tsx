import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { CustomerFilterSelect } from '../components/CustomerFilterSelect'
import { useStockItems } from '../hooks/useStockItems'
import type { StockItem } from '../types/stock'
import type { StockItemFilters } from '../types/stockFilters'
import { STOCK_ITEM_SOURCE } from '../utils/stockSources'

/**
 * Articles de stock.
 *
 * **Aucune colonne de quantité, et ce n'est pas un oubli.**
 * `StockItemListResource` n'expose aucun solde, et il n'existe pas de route
 * d'agrégat : afficher un total par ligne demanderait une requête par article,
 * exactement le N+1 que le §42 interdit. Les quantités se lisent sur la fiche
 * de l'article, où le serveur charge les soldes en une fois, ou dans
 * `/stock/balances` qui les pagine.
 */
export function StockItemListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [filters, setFilters] = useState<StockItemFilters>({
    page: 1,
    perPage: 25,
    sort: 'article_code',
    direction: 'asc',
  })

  const { data, isPending, error, refetch } = useStockItems(filters)

  const patch = (next: Partial<StockItemFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const text = (value: string | null | undefined) =>
    value === null || value === undefined || value === '' ? (
      <span className="text-muted-foreground">—</span>
    ) : (
      value
    )

  const columns: Column<StockItem>[] = [
    {
      key: 'articleCode',
      header: t('stock.fields.articleCode'),
      sortKey: 'article_code',
      cell: (row) => <span className="font-medium">{row.articleCode}</span>,
    },
    {
      key: 'barcode',
      header: t('stock.fields.barcode'),
      sortKey: 'barcode',
      hideOnMobile: true,
      cell: (row) => text(row.barcode),
    },
    {
      key: 'description',
      header: t('stock.fields.description'),
      hideOnMobile: true,
      cell: (row) => text(row.description),
    },
    {
      key: 'customer',
      header: t('stock.fields.customer'),
      cell: (row) => text(row.customerName),
    },
    {
      key: 'catalogItem',
      header: t('stock.fields.catalogItem'),
      hideOnMobile: true,
      cell: (row) =>
        row.catalogItemId === null ? (
          <span className="text-muted-foreground">{t('stock.notLinked')}</span>
        ) : (
          <span>{t('stock.linked')}</span>
        ),
    },
    {
      key: 'status',
      header: t('stock.fields.status'),
      sortKey: 'status',
      cell: (row) => <StatusBadge status={row.status} source={STOCK_ITEM_SOURCE} />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('stock.items')}
        description={t('stock.itemsSubtitle')}
        actions={
          <PermissionGuard permission="stock_items.create">
            <Button asChild>
              <Link to="/stock/items/create">
                <Plus className="size-4" aria-hidden />
                {t('stock.newItem')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(value) => patch({ search: value === '' ? undefined : value })}
          placeholder={t('stock.searchItems')}
        />
        <CustomerFilterSelect
          value={filters.customerId}
          onChange={(customerId) => patch({ customerId })}
        />
        <StatusFilterSelect
          source={STOCK_ITEM_SOURCE}
          value={filters.status}
          onChange={(status) => patch({ status })}
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
            direction:
              current.sort === sortKey && current.direction === 'asc' ? 'desc' : 'asc',
          }))
        }
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        onRowClick={(row) => void navigate(`/stock/items/${row.id}`)}
        emptyMessage={t('stock.noItem')}
      />
    </div>
  )
}
