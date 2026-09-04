import { useTranslation } from 'react-i18next'

import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDate } from '@/shared/utils/format'

import { orderAmounts } from '../../schemas/orderTotals'
import type { OrderDetail } from '../../types/orderDetail'
import { FieldGrid } from './FieldGrid'

const money = (value: number): string => value.toFixed(2)

/**
 * Vue d'ensemble d'une commande, en deux colonnes.
 *
 * À gauche ce qui décrit la commande — en-tête et remarques — à droite ce qui
 * s'y rattache : montants et contact. Tout vient de `GET /orders/{order}`,
 * chargée en un appel ; aucune requête supplémentaire ne remplit cet onglet.
 */
export function OrderSummaryTab({ order }: { order: OrderDetail }) {
  const { t } = useTranslation()
  const amounts = orderAmounts(order.services ?? [])

  // Le contact principal du premier service : la commande n'en porte pas
  // elle-même, ils sont attachés aux prestations.
  const contact = (order.services ?? [])
    .flatMap((service) => service.contacts ?? [])
    .find((item) => item.isPrimary)

  const header = [
    { labelKey: 'orders.fields.orderNumber', value: order.orderNumber },
    { labelKey: 'orders.fields.status', value: order.statusLabel },
    { labelKey: 'orders.fields.source', value: t(`orderSources.${order.source}`, order.source ?? '') },
    { labelKey: 'orders.fields.customer', value: order.customer?.name },
    { labelKey: 'orders.fields.agency', value: order.agency?.name },
    { labelKey: 'orders.fields.depot', value: order.depot?.name },
    { labelKey: 'orders.fields.orderDate', value: formatDate(order.orderDate) },
    { labelKey: 'orders.fields.orderType', value: order.orderType },
    { labelKey: 'orders.fields.groupCode', value: order.groupCode },
    { labelKey: 'orders.fields.externalReference', value: order.externalReference },
    { labelKey: 'orders.fields.customerReference', value: order.customerReference },
    { labelKey: 'orders.fields.currencyCode', value: order.currencyCode },
  ]

  const lines = [
    { labelKey: 'orders.fields.customerUnitPrice', value: money(amounts.customerUnitPrice) },
    { labelKey: 'orders.fields.customerTotalPrice', value: money(amounts.customerTotalPrice) },
    { labelKey: 'orders.fields.providerUnitCost', value: money(amounts.providerUnitCost) },
    { labelKey: 'orders.fields.providerTotalCost', value: money(amounts.providerTotalCost) },
  ]

  return (
    <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
      <div className="flex flex-col gap-4">
        <SectionCard
          title={t('orders.review.header')}
          description={t('orders.fields.currencyCode') + ' ' + (order.currencyCode ?? '—')}
        >
          <FieldGrid items={header} columns={3} />
        </SectionCard>

        <SectionCard title={t('orders.fields.internalRemark')}>
          <FieldGrid
            columns={2}
            items={[
              { labelKey: 'orders.fields.internalRemark', value: order.internalRemark },
              { labelKey: 'orders.fields.workerRemark', value: order.workerRemark },
            ]}
          />
        </SectionCard>
      </div>

      <div className="flex flex-col gap-4">
        <SectionCard
          title={t('orders.services.pricing')}
          description={t('orders.amountsFromServices')}
        >
          <dl className="flex flex-col gap-2">
            {lines.map((line) => (
              <div
                key={line.labelKey}
                className="flex items-baseline justify-between gap-3 border-b pb-1.5 last:border-0"
              >
                <dt className="text-sm text-muted-foreground">{t(line.labelKey)}</dt>
                <dd className="font-mono text-lg tabular-nums">{line.value}</dd>
              </div>
            ))}
          </dl>
        </SectionCard>

        <SectionCard title={t('orders.primaryContact')}>
          {contact === undefined ? (
            <p className="text-sm text-muted-foreground">{t('orders.services.noContact')}</p>
          ) : (
            <div className="flex items-center gap-2.5">
              <span className="grid size-9 shrink-0 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                {`${contact.firstName?.[0] ?? ''}${contact.lastName?.[0] ?? ''}`.toUpperCase() ||
                  '?'}
              </span>
              <div className="min-w-0">
                <p className="truncate font-semibold">
                  {`${contact.firstName ?? ''} ${contact.lastName ?? ''}`.trim() || '—'}
                </p>
                <p className="font-mono text-sm text-muted-foreground">
                  {contact.mobile ?? contact.phone ?? contact.email ?? '—'}
                </p>
              </div>
            </div>
          )}
        </SectionCard>
      </div>
    </div>
  )
}
