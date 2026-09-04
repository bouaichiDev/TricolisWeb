import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { OrderFilterBar } from '../components/OrderFilters'
import { OrderSourceBadge, OrderStatusBadge } from '../components/OrderStatusBadge'
import { useOrderList } from '../hooks/useOrders'
import { ORDER_SORTABLE, type OrderFilters, type OrderListItem } from '../types/order'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

const INITIAL: OrderFilters = { page: 1, perPage: 25, sort: 'order_date', direction: 'desc' }

/**
 * Liste des commandes — page centrale de l'exploitation.
 *
 * Le tri est borné à ce que `OrderListQuery` accepte : `order_number`,
 * `order_date`, `status`, `created_at`. Envoyer une autre colonne renvoie 422.
 *
 * Les compteurs de lignes et de services viennent de `withCount` côté serveur :
 * aucune requête supplémentaire n'est faite par ligne du tableau.
 */
export function OrderListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [filters, setFilters] = useState<OrderFilters>(INITIAL)

  const { data, isPending, error, refetch } = useOrderList(filters)

  const columns: Column<OrderListItem>[] = [
    {
      key: 'orderNumber',
      header: t('orders.fields.orderNumber'),
      sortKey: 'order_number',
      cell: (row) => (
        <Link
          to={`/orders/${row.id}`}
          className="font-medium text-primary hover:underline"
          onClick={(event) => event.stopPropagation()}
        >
          {row.orderNumber}
        </Link>
      ),
    },
    {
      key: 'customer',
      header: t('orders.fields.customer'),
      cell: (row) => row.customerName ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'agency',
      header: t('orders.fields.agency'),
      hideOnMobile: true,
      cell: (row) => row.agencyName ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'orderDate',
      header: t('orders.fields.orderDate'),
      sortKey: 'order_date',
      cell: (row) => formatDate(row.orderDate),
    },
    {
      key: 'content',
      header: t('orders.fields.content'),
      hideOnMobile: true,
      cell: (row) => (
        <span className="text-sm text-muted-foreground">
          {t('orders.lineCount', { count: row.lineCount })} ·{' '}
          {t('orders.serviceCount', { count: row.serviceCount })}
        </span>
      ),
    },
    {
      key: 'source',
      header: t('orders.fields.source'),
      hideOnMobile: true,
      cell: (row) => <OrderSourceBadge source={row.source} />,
    },
    {
      key: 'status',
      header: t('orders.fields.status'),
      sortKey: 'status',
      cell: (row) => <OrderStatusBadge status={row.status} label={row.statusLabel} />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('orders.title')}
        description={t('orders.subtitle')}
        actions={
          <PermissionGuard permission="orders.create">
            <Button asChild>
              <Link to="/orders/create">
                <Plus className="size-4" aria-hidden />
                {t('orders.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <OrderFilterBar
        filters={filters}
        onChange={(patch) => setFilters((current) => ({ ...current, ...patch, page: 1 }))}
        onReset={() => setFilters(INITIAL)}
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
        onSortChange={(sortKey) => {
          if (!ORDER_SORTABLE.includes(sortKey as (typeof ORDER_SORTABLE)[number])) return

          setFilters((current) => ({
            ...current,
            sort: sortKey,
            direction: current.sort === sortKey && current.direction === 'asc' ? 'desc' : 'asc',
          }))
        }}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        onRowClick={(row) => void navigate(`/orders/${row.id}`)}
        emptyMessage={t('orders.empty')}
      />
    </div>
  )
}
