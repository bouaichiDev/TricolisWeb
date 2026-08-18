import { useTranslation } from 'react-i18next'

import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDate, formatDateTime } from '@/shared/utils/format'

import type { OrderDetail } from '../../types/orderDetail'
import { OrderSourceBadge, OrderStatusBadge } from '../OrderStatusBadge'

/**
 * Vue d'ensemble d'une commande.
 *
 * Tout vient de `OrderDetailResource`, chargée en un appel : aucune requête
 * supplémentaire n'est faite pour remplir cet onglet.
 */
export function OrderSummaryTab({ order }: { order: OrderDetail }) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-6">
      <SectionCard title={t('orders.review.header')}>
        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <DetailField label={t('orders.fields.orderNumber')}>{order.orderNumber}</DetailField>
          <DetailField label={t('orders.fields.status')}>
            <OrderStatusBadge status={order.status} label={order.statusLabel} />
          </DetailField>
          <DetailField label={t('orders.fields.source')}>
            <OrderSourceBadge source={order.source} />
          </DetailField>
          <DetailField label={t('orders.fields.customer')}>{order.customer?.name}</DetailField>
          <DetailField label={t('orders.fields.agency')}>{order.agency?.name}</DetailField>
          <DetailField label={t('orders.fields.depot')}>{order.depot?.name}</DetailField>
          <DetailField label={t('orders.fields.orderDate')}>
            {formatDate(order.orderDate)}
          </DetailField>
          <DetailField label={t('orders.fields.orderType')}>{order.orderType}</DetailField>
          <DetailField label={t('orders.fields.groupCode')}>{order.groupCode}</DetailField>
          <DetailField label={t('orders.fields.externalReference')}>
            {order.externalReference}
          </DetailField>
          <DetailField label={t('orders.fields.customerReference')}>
            {order.customerReference}
          </DetailField>
          <DetailField label={t('orders.fields.currencyCode')}>{order.currencyCode}</DetailField>
        </dl>
      </SectionCard>

      <SectionCard title={t('orders.fields.content')}>
        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <DetailField label={t('orders.fields.weight')}>
            {order.weight === null ? undefined : String(order.weight)}
          </DetailField>
          <DetailField label={t('orders.fields.volume')}>
            {order.volume === null ? undefined : String(order.volume)}
          </DetailField>
          <DetailField label={t('orders.fields.packageCount')}>
            {order.packageCount === null ? undefined : String(order.packageCount)}
          </DetailField>
          <DetailField label={t('orders.lines.title')}>
            {t('orders.lineCount', { count: order.lines?.length ?? 0 })}
          </DetailField>
          <DetailField label={t('orders.services.title')}>
            {t('orders.serviceCount', { count: order.services?.length ?? 0 })}
          </DetailField>
          <DetailField label={t('orders.fields.createdAt')}>
            {formatDateTime(order.createdAt)}
          </DetailField>
        </dl>
      </SectionCard>

      {order.internalRemark !== null || order.workerRemark !== null ? (
        <SectionCard title={t('orders.fields.internalRemark')}>
          <dl className="grid gap-4 sm:grid-cols-2">
            <DetailField label={t('orders.fields.internalRemark')}>
              {order.internalRemark}
            </DetailField>
            <DetailField label={t('orders.fields.workerRemark')}>{order.workerRemark}</DetailField>
          </dl>
        </SectionCard>
      ) : null}
    </div>
  )
}
