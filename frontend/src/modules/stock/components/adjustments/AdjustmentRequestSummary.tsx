import { AlertTriangle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { formatDateTime } from '@/shared/utils/format'

import { AdjustmentDifference } from './AdjustmentDifference'
import {
  formatAdjustmentLocation,
  isZeroQuantity,
  type StockAdjustmentRequestDetail,
} from '../../types/stockAdjustment'
import { formatStockQuantity } from '../../utils/stockSources'

/**
 * Ce que le client a déclaré, et où en est le stock aujourd'hui.
 *
 * L'alerte de dérive vient de `drift`, calculé par le serveur : si le stock a
 * bougé depuis la demande, l'acceptation corrigera l'état **actuel** vers la
 * quantité constatée — pas l'écart déclaré. Le valideur doit le voir avant.
 */
export function AdjustmentRequestSummary({ request }: { request: StockAdjustmentRequestDetail }) {
  const { t } = useTranslation()
  const drifted = !isZeroQuantity(request.drift)

  return (
    <SectionCard title={t('stock.adjustments.sections.request')}>
      <dl className="grid gap-x-8 sm:grid-cols-3">
        <DetailField label={t('stock.fields.customer')}>
          <Link to={`/customers/${request.customerId}`} className="hover:underline">
            {request.customer?.name ?? request.customerId}
          </Link>
        </DetailField>
        <DetailField label={t('stock.fields.articleCode')}>
          <Link to={`/stock/items/${request.stockItemId}`} className="hover:underline">
            {request.stockItem?.articleCode ?? request.stockItemId}
          </Link>
          {request.stockItem?.description ? (
            <span className="block text-xs text-muted-foreground">{request.stockItem.description}</span>
          ) : null}
        </DetailField>
        <DetailField label={t('stock.fields.location')}>
          {formatAdjustmentLocation(request.location) ?? t('stock.adjustments.wholeItem')}
        </DetailField>

        <DetailField label={t('stock.adjustments.fields.displayed')}>
          {formatStockQuantity(request.displayedQuantity)}
        </DetailField>
        <DetailField label={t('stock.adjustments.fields.requested')}>
          {formatStockQuantity(request.requestedQuantity)}
        </DetailField>
        <DetailField label={t('stock.adjustments.fields.difference')}>
          <AdjustmentDifference value={request.difference} />
        </DetailField>

        <DetailField label={t('stock.adjustments.fields.current')}>
          {formatStockQuantity(request.currentQuantity)}
        </DetailField>
        <DetailField label={t('stock.adjustments.fields.requester')}>
          {request.requester?.name}
        </DetailField>
        <DetailField label={t('stock.adjustments.fields.createdAt')}>
          {formatDateTime(request.createdAt)}
        </DetailField>
      </dl>

      {drifted ? (
        <Alert className="mt-2" role="alert">
          <AlertTriangle className="size-4" aria-hidden />
          <AlertDescription>
            {t('stock.adjustments.driftAlert', {
              displayed: formatStockQuantity(request.displayedQuantity),
              current: formatStockQuantity(request.currentQuantity),
            })}
          </AlertDescription>
        </Alert>
      ) : null}

      <dl className="mt-2 grid gap-x-8 sm:grid-cols-2">
        <DetailField label={t('stock.adjustments.fields.reason')}>{request.reason}</DetailField>
        <DetailField label={t('stock.adjustments.fields.comment')}>{request.comment}</DetailField>
      </dl>
    </SectionCard>
  )
}
