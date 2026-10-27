import { Link } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import type { Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDate } from '@/shared/utils/format'

import type { OrderListItem } from '../types'

/** Colonnes de la liste des commandes ; les clés de tri sont celles du backend. */
export const ordersColumns: Column<OrderListItem>[] = [
  {
    key: 'reference',
    header: 'Référence',
    sortKey: 'reference',
    cell: (order) => (
      <Link to={paths.order(order.id)} className="text-primary font-medium hover:underline">
        {order.reference}
      </Link>
    ),
  },
  { key: 'customerReference', header: 'Votre référence', cell: (order) => order.customerReference ?? '—' },
  { key: 'orderDate', header: 'Date', sortKey: 'orderDate', cell: (order) => formatDate(order.orderDate) },
  {
    key: 'requested',
    header: 'Livraison souhaitée',
    hideOnMobile: true,
    cell: (order) => formatDate(order.firstRequestedDate),
  },
  {
    key: 'status',
    header: 'Statut',
    sortKey: 'status',
    cell: (order) => <StatusBadge code={order.status.code} label={order.status.label} color={order.status.color} />,
  },
  {
    key: 'packages',
    header: 'Colis',
    hideOnMobile: true,
    className: 'text-right',
    cell: (order) => <span className="tabular-nums">{order.packageCount}</span>,
  },
]
