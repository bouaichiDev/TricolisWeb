import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable } from '@/shared/components/data/DataTable'
import { SectionCard } from '@/shared/components/layout/SectionCard'

import { adjustmentColumns, adjustmentRowActions } from './adjustmentColumns'
import { useCustomerStockAdjustmentRequests } from '../../hooks/useStockAdjustmentRequests'
import type { StockAdjustmentRequestFilters } from '../../types/stockAdjustment'

/**
 * Les demandes de correction d'un client, sur l'onglet Stock de sa fiche.
 *
 * Servies par `customers/{c}/stock-adjustment-requests` : le filtre client est
 * posé par le serveur, pas trié en mémoire sur une liste globale.
 */
function CustomerAdjustmentTable({ customerId }: { customerId: string }) {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<StockAdjustmentRequestFilters>({ page: 1, perPage: 10 })
  const { data, isPending, error, refetch } = useCustomerStockAdjustmentRequests(customerId, filters)

  return (
    <SectionCard
      title={t('stock.adjustments.title')}
      description={t('stock.adjustments.customerHint')}
    >
      <DataTable
        columns={adjustmentColumns(t, { withCustomer: false })}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onPerPageChange={(perPage) => setFilters((current) => ({ ...current, perPage, page: 1 }))}
        onRetry={() => void refetch()}
        actions={adjustmentRowActions(t)}
        emptyMessage={t('stock.adjustments.emptyForCustomer')}
      />
    </SectionCard>
  )
}

export function CustomerAdjustmentRequests({ customerId }: { customerId: string }) {
  return (
    <PermissionGuard permission="stock_adjustment_requests.view">
      <CustomerAdjustmentTable customerId={customerId} />
    </PermissionGuard>
  )
}
