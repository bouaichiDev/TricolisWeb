import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDateTime } from '@/shared/utils/format'

import type { StockAdjustmentRequestDetail } from '../../types/stockAdjustment'
import { formatStockQuantity, STOCK_ADJUSTMENT_REQUEST_SOURCE } from '../../utils/stockSources'

/** La décision rendue — ou son attente — et le mouvement qu'elle a créé. */
export function AdjustmentDecision({ request }: { request: StockAdjustmentRequestDetail }) {
  const { t } = useTranslation()
  const movement = request.stockMovement

  return (
    <SectionCard title={t('stock.adjustments.sections.decision')}>
      <dl className="grid gap-x-8 sm:grid-cols-2">
        <DetailField label={t('stock.fields.status')}>
          <StatusBadge status={request.status} source={STOCK_ADJUSTMENT_REQUEST_SOURCE} />
        </DetailField>
        <DetailField label={t('stock.adjustments.fields.reviewer')}>
          {request.reviewer?.name}
        </DetailField>
        <DetailField label={t('stock.adjustments.fields.reviewedAt')}>
          {request.reviewedAt ? formatDateTime(request.reviewedAt) : null}
        </DetailField>
        <DetailField label={t('stock.adjustments.fields.reviewComment')}>
          {request.reviewComment}
        </DetailField>
        <DetailField label={t('stock.adjustments.fields.movement')}>
          {movement ? (
            <Link to={`/stock/movements/${movement.id}`} className="hover:underline">
              {t('stock.adjustments.movementLink', {
                type: movement.movementType,
                quantity: formatStockQuantity(movement.quantity),
              })}
            </Link>
          ) : null}
        </DetailField>
      </dl>
    </SectionCard>
  )
}
