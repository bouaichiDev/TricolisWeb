import { Link } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import type { Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'

import type { StockItem, StockLocation } from '../types'
import { formatQuantity } from '../utils/quantity'

const quantity = (value: string) => <span className="tabular-nums">{formatQuantity(value)}</span>

/** « Dépôt central · A-01-01 (zone A, allée 01…) » — ce qu'il faut pour retrouver sa marchandise. */
export function locationLabel(location: StockLocation | null): string {
  if (location === null) return '—'

  const detail = [location.zoneCode && `zone ${location.zoneCode}`, location.aisle && `allée ${location.aisle}`, location.rack && `rack ${location.rack}`, location.level && `niveau ${location.level}`]
    .filter(Boolean)
    .join(', ')

  return `${location.depot ? `${location.depot} · ` : ''}${location.locationCode}${detail ? ` (${detail})` : ''}`
}

/** Colonnes des articles en stock (§8) ; les clés de tri sont celles du backend. */
export const stockColumns: Column<StockItem>[] = [
  {
    key: 'articleCode',
    header: 'Code article',
    sortKey: 'articleCode',
    cell: (item) => (
      <Link to={paths.stockItem(item.id)} className="text-primary font-medium hover:underline">
        {item.articleCode}
      </Link>
    ),
  },
  { key: 'description', header: 'Description', sortKey: 'description', cell: (item) => item.description ?? '—' },
  { key: 'barcode', header: 'Code-barres', hideOnMobile: true, cell: (item) => item.barcode ?? '—' },
  { key: 'quantity', header: 'Quantité', sortKey: 'quantity', className: 'text-right', cell: (item) => quantity(item.stock.quantity) },
  { key: 'reserved', header: 'Réservée', hideOnMobile: true, className: 'text-right', cell: (item) => quantity(item.stock.reservedQuantity) },
  { key: 'available', header: 'Disponible', sortKey: 'availableQuantity', className: 'text-right', cell: (item) => quantity(item.stock.availableQuantity) },
  { key: 'status', header: 'Statut', sortKey: 'status', cell: (item) => <StatusBadge code={item.status.code} label={item.status.label} color={item.status.color} /> },
]
