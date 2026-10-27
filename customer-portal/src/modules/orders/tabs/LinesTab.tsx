import { DataTable, type Column } from '@/shared/components/data/DataTable'

import type { OrderDetail, OrderLine } from '../types'

const num = (value: string | null) => (value === null ? '—' : String(Number(value)))

const columns: Column<OrderLine>[] = [
  { key: 'code', header: 'Article', cell: (line) => line.articleCode ?? '—' },
  { key: 'name', header: 'Désignation', cell: (line) => line.name ?? '—' },
  { key: 'quantity', header: 'Quantité', className: 'text-right', cell: (line) => <span className="tabular-nums">{num(line.quantity)}</span> },
  { key: 'delivered', header: 'Livrée', className: 'text-right', hideOnMobile: true, cell: (line) => <span className="tabular-nums">{num(line.deliveredQuantity)}</span> },
  { key: 'weight', header: 'Poids unitaire (kg)', className: 'text-right', hideOnMobile: true, cell: (line) => <span className="tabular-nums">{line.weight ?? '—'}</span> },
]

export function LinesTab({ order }: { order: OrderDetail }) {
  return <DataTable columns={columns} rows={order.lines} rowKey={(line) => line.id} emptyMessage="Aucun article." />
}
