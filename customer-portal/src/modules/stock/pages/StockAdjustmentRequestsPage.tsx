import { useQuery } from '@tanstack/react-query'
import { Eye } from 'lucide-react'
import { Link } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { formatDateTime } from '@/shared/utils/format'

import type { StockAdjustmentRequest } from '../adjustmentTypes'
import { adjustmentsApi } from '../api/adjustmentsApi'
import { NewAdjustmentRequest } from '../components/NewAdjustmentRequest'
import { StockNav } from '../components/StockNav'
import { locationLabel } from '../components/stockColumns'
import { useStockFilters } from '../hooks/useStockFilters'
import { formatQuantity } from '../utils/quantity'

const columns: Column<StockAdjustmentRequest>[] = [
  { key: 'createdAt', header: 'Demandée le', cell: (request) => formatDateTime(request.createdAt) },
  { key: 'item', header: 'Article', cell: (request) => <Link to={paths.stockItem(request.stockItem.id)} className="text-primary hover:underline">{request.stockItem.articleCode}</Link> },
  { key: 'location', header: 'Emplacement', hideOnMobile: true, cell: (request) => (request.location ? locationLabel(request.location) : 'Tout l’article') },
  { key: 'displayed', header: 'Affichée', className: 'text-right', cell: (request) => <span className="tabular-nums">{formatQuantity(request.displayedQuantity)}</span> },
  { key: 'requested', header: 'Constatée', className: 'text-right', cell: (request) => <span className="tabular-nums">{formatQuantity(request.requestedQuantity)}</span> },
  { key: 'reason', header: 'Motif', hideOnMobile: true, cell: (request) => request.reason },
  { key: 'status', header: 'Statut', cell: (request) => <StatusBadge code={request.status.code} label={request.status.label} color={request.status.color} /> },
  { key: 'answer', header: 'Réponse', hideOnMobile: true, cell: (request) => request.reviewComment ?? (request.isPending ? 'En attente de votre transporteur' : '—') },
]

/** Les demandes de correction du client, leur décision (§22), et « Nouvelle demande ». */
export function StockAdjustmentRequestsPage() {
  const { filters, update } = useStockFilters()
  const requests = useQuery({ queryKey: ['stock', 'adjustment-requests', filters.page ?? 1, filters.perPage], queryFn: () => adjustmentsApi.list({ page: filters.page, perPage: filters.perPage }) })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Demandes de correction"
        description="Les écarts que vous avez signalés, et la décision de votre transporteur."
        actions={<NewAdjustmentRequest />}
      />
      <StockNav />
      <DataTable
        columns={columns}
        rows={requests.data?.data ?? []}
        rowKey={(request) => request.id}
        meta={requests.data?.meta}
        isLoading={requests.isPending}
        errorMessage={requests.isError ? errorMessage(requests.error) : null}
        onRetry={() => void requests.refetch()}
        onPageChange={(page) => update({ page })}
        onPerPageChange={(perPage) => update({ perPage })}
        emptyMessage="Aucune demande de correction."
        actions={(request) => <RowActions actions={[{ key: 'item', icon: Eye, label: 'Voir l’article', to: paths.stockItem(request.stockItem.id) }]} />}
      />
    </div>
  )
}
