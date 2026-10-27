import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { AdjustmentDecision } from '../components/adjustments/AdjustmentDecision'
import { AdjustmentRequestSummary } from '../components/adjustments/AdjustmentRequestSummary'
import { ApproveAdjustmentDialog } from '../components/adjustments/ApproveAdjustmentDialog'
import { RejectAdjustmentDialog } from '../components/adjustments/RejectAdjustmentDialog'
import { useStockAdjustmentRequest } from '../hooks/useStockAdjustmentRequests'
import { STOCK_ADJUSTMENT_REQUEST_SOURCE } from '../utils/stockSources'

/**
 * Fiche d'une demande de correction.
 *
 * Les boutons suivent `actions`, **calculées par le serveur** — statut en
 * attente et permission `review`. L'écran ne les recalcule jamais : deux
 * onglets ouverts suffiraient à le contredire, et c'est le 409 qui tranche.
 */
export function StockAdjustmentRequestDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams<{ id: string }>()
  const [dialog, setDialog] = useState<'approve' | 'reject' | null>(null)

  const { data: request, isPending, error, refetch } = useStockAdjustmentRequest(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!request) return null

  const close = (open: boolean) => {
    if (!open) setDialog(null)
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('stock.adjustments.detailTitle', {
          article: request.stockItem?.articleCode ?? request.stockItemId,
        })}
        description={request.customer?.name}
        actions={
          <div className="flex items-center gap-2">
            <StatusBadge status={request.status} source={STOCK_ADJUSTMENT_REQUEST_SOURCE} />
            {request.actions.canReject ? (
              <Button variant="outline" onClick={() => setDialog('reject')}>
                {t('stock.adjustments.reject')}
              </Button>
            ) : null}
            {request.actions.canApprove ? (
              <Button onClick={() => setDialog('approve')}>{t('stock.adjustments.approve')}</Button>
            ) : null}
          </div>
        }
      />

      <AdjustmentRequestSummary request={request} />
      <AdjustmentDecision request={request} />

      {request.actions.canApprove ? (
        <ApproveAdjustmentDialog request={request} open={dialog === 'approve'} onOpenChange={close} />
      ) : null}
      {request.actions.canReject ? (
        <RejectAdjustmentDialog requestId={request.id} open={dialog === 'reject'} onOpenChange={close} />
      ) : null}
    </div>
  )
}
