import { Plus } from 'lucide-react'
import { useState } from 'react'

import { Can } from '@/app/guards/Can'
import { errorMessage } from '@/shared/api/errors'
import { DataTable } from '@/shared/components/data/DataTable'
import { InlineLoader } from '@/shared/components/feedback/States'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { StockFiltersBar } from '../components/StockFiltersBar'
import { StockItemDialog } from '../components/StockItemDialog'
import { StockRowActions } from '../components/StockRowActions'
import { StockNav } from '../components/StockNav'
import { StockTotalsCards } from '../components/StockTotalsCards'
import { stockColumns } from '../components/stockColumns'
import { useStockItems, useStockSummary } from '../hooks/useStock'
import { useStockFilters } from '../hooks/useStockFilters'

/**
 * Le stock du client chez le transporteur (§8) : résumé calculé au backend,
 * recherche, filtres, tableau paginé. Le stockage est optionnel : sans
 * article, la page le dit simplement.
 */
export function StockPage() {
  const { filters, update, toggleSort, reset } = useStockFilters()
  const summary = useStockSummary()
  const items = useStockItems(filters)
  const [adding, setAdding] = useState(false)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Stock"
        description="Les articles que votre transporteur conserve pour vous, et leurs quantités par emplacement."
        actions={
          <Can permission="customer.stock.items.create">
            <Button onClick={() => setAdding(true)}>
              <Plus className="size-4" aria-hidden /> Ajouter un article
            </Button>
          </Can>
        }
      />
      <StockNav />

      {summary.data ? <StockTotalsCards totals={summary.data} itemsCount={summary.data.itemsCount} /> : <InlineLoader />}

      <StockFiltersBar filters={filters} options={items.data?.meta.filters} onChange={update} onReset={reset} />

      <DataTable
        columns={stockColumns}
        rows={items.data?.data ?? []}
        rowKey={(item) => item.id}
        meta={items.data?.meta}
        isLoading={items.isPending}
        errorMessage={items.isError ? errorMessage(items.error) : null}
        onRetry={() => void items.refetch()}
        sort={filters.sort ?? 'articleCode'}
        direction={filters.direction ?? 'asc'}
        onSortChange={toggleSort}
        onPageChange={(page) => update({ page })}
        onPerPageChange={(perPage) => update({ perPage })}
        emptyMessage="Aucun article en stock."
        actions={(item) => <StockRowActions item={item} />}
      />

      <StockItemDialog open={adding} onOpenChange={setAdding} />
    </div>
  )
}
