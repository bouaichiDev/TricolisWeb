import { useTranslation } from 'react-i18next'

import { orderAmounts, orderCounts } from '../../schemas/orderTotals'
import type { OrderDetail } from '../../types/orderDetail'

const show = (value: number | string | null | undefined): string =>
  value === null || value === undefined ? '—' : String(value)

const money = (value: number): string => value.toFixed(2)

/**
 * Bandeau de chiffres clés.
 *
 * Poids, volume et nombre de colis sont **calculés par le serveur** —
 * `RecalculateOrderTotals` les dérive du contenu à chaque écriture. Les
 * montants, eux, sont sommés depuis les services : la commande n'en porte
 * aucun.
 */
export function OrderKpiStrip({ order }: { order: OrderDetail }) {
  const { t } = useTranslation()
  const counts = orderCounts(order)
  const amounts = orderAmounts(order.services ?? [])

  const tiles = [
    { key: 'weight', value: show(order.weight), unit: t('orders.kpi.kg') },
    { key: 'volume', value: show(order.volume), unit: t('orders.kpi.m3') },
    { key: 'packages', value: show(order.packageCount ?? counts.packages), unit: t('orders.kpi.unit') },
    { key: 'lines', value: String(counts.lines), unit: t('orders.kpi.lineUnit') },
    { key: 'services', value: String(counts.services), unit: t('orders.kpi.serviceUnit') },
    {
      key: 'customerTotal',
      value: money(amounts.customerTotalPrice),
      unit: order.currencyCode ?? '',
    },
  ]

  return (
    <dl className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
      {tiles.map((tile) => (
        <div key={tile.key} className="rounded-lg border bg-card px-3.5 py-3">
          <dt className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            {t(`orders.kpi.${tile.key}`)}
          </dt>
          <dd className="mt-0.5 font-mono text-2xl leading-8 tabular-nums">{tile.value}</dd>
          <p className="text-xs text-muted-foreground">{tile.unit}</p>
        </div>
      ))}
    </dl>
  )
}
