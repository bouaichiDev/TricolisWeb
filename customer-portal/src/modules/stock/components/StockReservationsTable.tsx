import { Link } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDateTime } from '@/shared/utils/format'

import { useStockReservations } from '../hooks/useStock'
import type { StockFilters, StockReservation } from '../types'
import { formatQuantity } from '../utils/quantity'
import { locationLabel } from './stockColumns'

const columns: Column<StockReservation>[] = [
  {
    key: 'item',
    header: 'Article',
    cell: (reservation) => (
      <Link to={paths.stockItem(reservation.stockItem.id)} className="text-primary hover:underline">
        {reservation.stockItem.articleCode}
      </Link>
    ),
  },
  {
    key: 'order',
    header: 'Commande',
    cell: (reservation) => (reservation.order ? <Link to={paths.order(reservation.order.id)} className="text-primary hover:underline">{reservation.order.reference}</Link> : '—'),
  },
  { key: 'line', header: 'Ligne', hideOnMobile: true, cell: (reservation) => reservation.orderLine?.name ?? reservation.orderLine?.articleCode ?? '—' },
  { key: 'location', header: 'Emplacement', hideOnMobile: true, cell: (reservation) => locationLabel(reservation.location) },
  { key: 'quantity', header: 'Quantité', className: 'text-right', cell: (reservation) => <span className="tabular-nums">{formatQuantity(reservation.quantity)}</span> },
  { key: 'status', header: 'Statut', cell: (reservation) => <StatusBadge code={reservation.status.code} label={reservation.status.label} color={reservation.status.color} /> },
  { key: 'reservedAt', header: 'Réservée le', hideOnMobile: true, cell: (reservation) => formatDateTime(reservation.reservedAt) },
  { key: 'releasedAt', header: 'Libérée le', hideOnMobile: true, cell: (reservation) => formatDateTime(reservation.releasedAt) },
]

interface StockReservationsTableProps {
  filters: StockFilters
  onPageChange: (page: number) => void
}

/** Les réservations du client pour ses commandes (§17) — lecture seule. */
export function StockReservationsTable({ filters, onPageChange }: StockReservationsTableProps) {
  const reservations = useStockReservations(filters)

  return (
    <DataTable
      columns={columns}
      rows={reservations.data?.data ?? []}
      rowKey={(reservation) => reservation.id}
      meta={reservations.data?.meta}
      isLoading={reservations.isPending}
      errorMessage={reservations.isError ? errorMessage(reservations.error) : null}
      onRetry={() => void reservations.refetch()}
      onPageChange={onPageChange}
      emptyMessage="Aucune réservation."
    />
  )
}
