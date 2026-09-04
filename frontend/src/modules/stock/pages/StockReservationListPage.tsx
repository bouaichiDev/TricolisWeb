import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ControlledCheckbox } from '@/shared/components/form/ControlledCheckbox'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { useStockReservations } from '../hooks/useStockReservations'
import { isReleased, type StockReservation } from '../types/stock'
import type { StockReservationFilters } from '../types/stockFilters'
import { formatStockQuantity, STOCK_RESERVATION_SOURCE } from '../utils/stockSources'

/**
 * Réservations de stock.
 *
 * Une réservation libérée **reste dans la liste** : elle a existé, et sa trace
 * vaut pour l'audit. La colonne « Libérée le » distingue les deux d'un coup
 * d'œil, et le filtre « en cours seulement » écarte les autres quand c'est ce
 * qu'on cherche.
 *
 * Ce filtre s'appuie sur `releasedFrom` / `releasedTo`, les seuls que
 * `ListStockReservationRequest` accepte sur cette colonne. Il n'existe pas de
 * `released=false` : « en cours » se traduit donc par le statut, qui est ce que
 * le référentiel décrit.
 */
export function StockReservationListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [filters, setFilters] = useState<StockReservationFilters>({
    page: 1,
    perPage: 25,
    sort: 'reserved_at',
    direction: 'desc',
  })

  const { data, isPending, error, refetch } = useStockReservations(filters)

  const patch = (next: Partial<StockReservationFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const rows = data?.data ?? []
  const [openOnly, setOpenOnly] = useState(false)
  const visible = openOnly ? rows.filter((row) => !isReleased(row)) : rows

  const columns: Column<StockReservation>[] = [
    {
      key: 'reservedAt',
      header: t('stock.fields.reservedAt'),
      sortKey: 'reserved_at',
      cell: (row) => formatDateTime(row.reservedAt),
    },
    {
      key: 'article',
      header: t('stock.fields.articleCode'),
      cell: (row) => (
        <Link to={`/stock/items/${row.stockItemId}`} className="hover:underline">
          {row.stockItemId}
        </Link>
      ),
    },
    {
      key: 'location',
      header: t('stock.fields.location'),
      hideOnMobile: true,
      cell: (row) => (
        <Link to={`/stock/locations/${row.stockLocationId}`} className="hover:underline">
          {row.stockLocationId}
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
    {
      key: 'releasedAt',
      header: t('stock.fields.releasedAt'),
      sortKey: 'released_at',
      hideOnMobile: true,
      cell: (row) =>
        isReleased(row) ? (
          formatDateTime(row.releasedAt)
        ) : (
          <span className="text-muted-foreground">{t('stock.stillHeld')}</span>
        ),
    },
    {
      key: 'status',
      header: t('stock.fields.status'),
      sortKey: 'status',
      cell: (row) => <StatusBadge status={row.status} source={STOCK_RESERVATION_SOURCE} />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('stock.reservations')}
        description={t('stock.reservationsSubtitle')}
        actions={
          <PermissionGuard permission="stock_reservations.create">
            <Button asChild>
              <Link to="/stock/reservations/create">
                <Plus className="size-4" aria-hidden />
                {t('stock.newReservation')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <StatusFilterSelect
          source={STOCK_RESERVATION_SOURCE}
          value={filters.status}
          onChange={(status) => patch({ status })}
        />
        {/* Filtre d'affichage, sur la page courante : l'API n'expose pas de
            « non libérée ». Le filtre de statut, lui, est bien serveur. */}
        <ControlledCheckbox
          label={t('stock.openOnly')}
          checked={openOnly}
          onChange={setOpenOnly}
          description={t('stock.openOnlyHint')}
        />
      </div>

      <DataTable
        columns={columns}
        rows={visible}
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
        onRowClick={(row) => void navigate(`/stock/reservations/${row.id}`)}
        emptyMessage={t('stock.noReservation')}
      />
    </div>
  )
}
