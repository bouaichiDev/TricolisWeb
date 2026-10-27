import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'

import type { OrderDetail, OrderPackage } from '../types'

export function PackagesTab({ order }: { order: OrderDetail }) {
  const lineName = new Map(order.lines.map((line) => [line.id, line.name ?? line.articleCode ?? 'Ligne']))
  const position = new Map(order.packages.map((pkg, index) => [pkg.id, index + 1]))

  const columns: Column<OrderPackage>[] = [
    { key: 'number', header: '#', cell: (pkg) => position.get(pkg.id) },
    { key: 'type', header: 'Type', cell: (pkg) => [pkg.packageType?.name, pkg.groupingType?.name].filter(Boolean).join(' / ') || '—' },
    { key: 'reference', header: 'Référence', cell: (pkg) => pkg.reference ?? pkg.barcode ?? '—' },
    {
      key: 'content',
      header: 'Contenu',
      hideOnMobile: true,
      cell: (pkg) =>
        pkg.lines.map((line) => `${Number(line.quantity)} × ${lineName.get(line.orderLineId) ?? '?'}`).join(', ') || '—',
    },
    { key: 'weight', header: 'Poids (kg)', className: 'text-right', cell: (pkg) => <span className="tabular-nums">{pkg.weight ?? '—'}</span> },
    { key: 'status', header: 'Statut', cell: (pkg) => <StatusBadge code={pkg.status.code} label={pkg.status.label} color={pkg.status.color} /> },
  ]

  return <DataTable columns={columns} rows={order.packages} rowKey={(pkg) => pkg.id} emptyMessage="Aucun colis sur cette commande." />
}
