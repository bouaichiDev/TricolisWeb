import { Plus } from 'lucide-react'
import { Link } from 'react-router-dom'

import { Can } from '@/app/guards/Can'
import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { DataTable } from '@/shared/components/data/DataTable'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { OrderFiltersBar } from '../components/OrderFiltersBar'
import { OrderRowActions } from '../components/OrderRowActions'
import { ordersColumns } from '../components/ordersColumns'
import { useOrderFilters } from '../hooks/useOrderFilters'
import { useFormOptions, useOrders } from '../hooks/useOrders'

export function OrdersPage() {
  const { filters, update, toggleSort, reset } = useOrderFilters()
  const orders = useOrders(filters)
  const options = useFormOptions()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Commandes"
        description="Les commandes de votre entreprise auprès de votre transporteur."
        actions={
          <Can permission="customer.orders.create">
            <Button asChild>
              <Link to={paths.orderCreate}>
                <Plus className="size-4" aria-hidden /> Nouvelle commande
              </Link>
            </Button>
          </Can>
        }
      />

      <OrderFiltersBar filters={filters} options={options.data} onChange={update} onReset={reset} />

      <DataTable
        columns={ordersColumns}
        rows={orders.data?.data ?? []}
        rowKey={(order) => order.id}
        meta={orders.data?.meta}
        isLoading={orders.isPending}
        errorMessage={orders.isError ? errorMessage(orders.error) : null}
        onRetry={() => void orders.refetch()}
        sort={filters.sort ?? 'orderDate'}
        direction={filters.direction ?? 'desc'}
        onSortChange={toggleSort}
        onPageChange={(page) => update({ page })}
        onPerPageChange={(perPage) => update({ perPage })}
        emptyMessage="Aucune commande ne correspond."
        actions={(order) => <OrderRowActions order={order} />}
      />
    </div>
  )
}
