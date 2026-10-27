import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { DataTable } from '@/shared/components/data/DataTable'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { adjustmentColumns, adjustmentRowActions } from '../components/adjustments/adjustmentColumns'
import { AdjustmentFilterBar } from '../components/adjustments/AdjustmentFilterBar'
import { useStockAdjustmentRequests } from '../hooks/useStockAdjustmentRequests'
import type { StockAdjustmentRequestFilters } from '../types/stockAdjustment'

/**
 * Demandes de correction déposées au portail client.
 *
 * Sans tri choisi, le serveur rend les demandes **en attente d'abord**, puis
 * les plus récentes : c'est la file de travail. Choisir une colonne remplace
 * cet ordre ; aucun bouton de création — une demande naît chez le client.
 */
export function StockAdjustmentRequestListPage() {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<StockAdjustmentRequestFilters>({ page: 1, perPage: 25 })

  const { data, isPending, error, refetch } = useStockAdjustmentRequests(filters)

  const patch = (next: Partial<StockAdjustmentRequestFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('stock.adjustments.title')} description={t('stock.adjustments.subtitle')} />

      <AdjustmentFilterBar filters={filters} onChange={patch} />

      <DataTable
        columns={adjustmentColumns(t)}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        sort={filters.sort}
        direction={filters.direction}
        onSortChange={(sortKey) =>
          setFilters((current) => ({
            ...current,
            sort: sortKey as StockAdjustmentRequestFilters['sort'],
            direction: current.sort === sortKey && current.direction === 'desc' ? 'asc' : 'desc',
          }))
        }
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onPerPageChange={(perPage) => setFilters((current) => ({ ...current, perPage, page: 1 }))}
        onRetry={() => void refetch()}
        actions={adjustmentRowActions(t)}
        emptyMessage={t('stock.adjustments.empty')}
      />
    </div>
  )
}
