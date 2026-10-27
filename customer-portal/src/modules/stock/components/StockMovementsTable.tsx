import { Link } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { formatDateTime } from '@/shared/utils/format'

import { useStockMovements } from '../hooks/useStock'
import type { StockFilters, StockMovement } from '../types'
import { DIRECTION_LABELS, formatQuantity } from '../utils/quantity'
import { locationLabel } from './stockColumns'

const columns: Column<StockMovement>[] = [
  { key: 'createdAt', header: 'Date', cell: (movement) => formatDateTime(movement.createdAt) },
  {
    key: 'item',
    header: 'Article',
    cell: (movement) => (
      <Link to={paths.stockItem(movement.stockItem.id)} className="text-primary hover:underline">
        {movement.stockItem.articleCode}
      </Link>
    ),
  },
  { key: 'direction', header: 'Sens', cell: (movement) => DIRECTION_LABELS[movement.direction] ?? movement.direction },
  // Le type tel que le transporteur l'a enregistré : aucune traduction inventée.
  { key: 'type', header: 'Type', hideOnMobile: true, cell: (movement) => movement.movementType },
  { key: 'quantity', header: 'Quantité', className: 'text-right', cell: (movement) => <span className="tabular-nums">{formatQuantity(movement.quantity)}</span> },
  { key: 'from', header: 'De', hideOnMobile: true, cell: (movement) => locationLabel(movement.sourceLocation) },
  { key: 'to', header: 'Vers', hideOnMobile: true, cell: (movement) => locationLabel(movement.destinationLocation) },
]

interface StockMovementsTableProps {
  filters: StockFilters
  onPageChange: (page: number) => void
}

/** Les mouvements du client, du plus récent au plus ancien (§16) — lecture seule. */
export function StockMovementsTable({ filters, onPageChange }: StockMovementsTableProps) {
  const movements = useStockMovements(filters)

  return (
    <DataTable
      columns={columns}
      rows={movements.data?.data ?? []}
      rowKey={(movement) => movement.id}
      meta={movements.data?.meta}
      isLoading={movements.isPending}
      errorMessage={movements.isError ? errorMessage(movements.error) : null}
      onRetry={() => void movements.refetch()}
      onPageChange={onPageChange}
      emptyMessage="Aucun mouvement."
    />
  )
}
