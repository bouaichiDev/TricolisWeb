import { ArrowDownToLine, ArrowUpFromLine, MoveRight, Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { useStockMovements } from '../hooks/useStockMovements'
import { movementDirection, type StockMovement } from '../types/stock'
import type { StockMovementFilters } from '../types/stockFilters'
import { formatStockQuantity } from '../utils/stockSources'

const ICONS = {
  entry: ArrowDownToLine,
  exit: ArrowUpFromLine,
  transfer: MoveRight,
}

/**
 * Journal des mouvements de stock.
 *
 * **Ni modification, ni suppression.** La route n'expose que `index`, `store` et
 * `show` : un mouvement est un fait daté, et le corriger reviendrait à réécrire
 * l'histoire d'un solde. Une erreur se rattrape par un mouvement de plus.
 *
 * La recherche porte sur `movementType` et `sourceEntityType` — c'est ce que
 * `StockMovementListQuery` accepte, et rien d'autre. Le sens n'est pas cherché :
 * il n'est pas stocké, il se déduit des emplacements.
 */
export function StockMovementListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [filters, setFilters] = useState<StockMovementFilters>({
    page: 1,
    perPage: 25,
    sort: 'created_at',
    direction: 'desc',
  })

  const { data, isPending, error, refetch } = useStockMovements(filters)

  const columns: Column<StockMovement>[] = [
    {
      key: 'createdAt',
      header: t('stock.fields.createdAt'),
      sortKey: 'created_at',
      cell: (row) => formatDateTime(row.createdAt),
    },
    {
      key: 'direction',
      header: t('stock.fields.direction'),
      cell: (row) => {
        const direction = movementDirection(row)
        const Icon = ICONS[direction]

        return (
          <span className="flex items-center gap-1.5">
            <Icon className="size-4 text-muted-foreground" aria-hidden />
            {t(`stock.directions.${direction}`)}
          </span>
        )
      },
    },
    {
      key: 'movementType',
      header: t('stock.fields.movementType'),
      sortKey: 'movement_type',
      hideOnMobile: true,
      cell: (row) => row.movementType,
    },
    {
      key: 'stockItem',
      header: t('stock.fields.articleCode'),
      cell: (row) => (
        <Link to={`/stock/items/${row.stockItemId}`} className="hover:underline">
          {row.stockItemId}
        </Link>
      ),
    },
    {
      key: 'quantity',
      header: t('stock.fields.quantity'),
      sortKey: 'quantity',
      cell: (row) => (
        <span className="font-medium">{formatStockQuantity(row.quantity)}</span>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('stock.movementsTitle')}
        description={t('stock.movementsSubtitle')}
        actions={
          <PermissionGuard permission="stock_movements.create">
            <Button asChild>
              <Link to="/stock/movements/create">
                <Plus className="size-4" aria-hidden />
                {t('stock.newMovement')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <SearchInput
        value={filters.search ?? ''}
        onChange={(value) =>
          setFilters((current) => ({
            ...current,
            page: 1,
            search: value === '' ? undefined : value,
          }))
        }
        placeholder={t('stock.searchMovements')}
      />

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
        onRowClick={(row) => void navigate(`/stock/movements/${row.id}`)}
        emptyMessage={t('stock.noMovement')}
      />
    </div>
  )
}
