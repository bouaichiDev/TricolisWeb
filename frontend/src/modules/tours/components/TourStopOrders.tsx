import { Package, Timer, Weight, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import type { StopOrder } from '../types/tour'

interface TourStopOrdersProps {
  orders: StopOrder[]
  /** Absent quand la tournée ne laisse rien retirer. */
  onUnplan?: (orderServiceIds: string[]) => void
}

/**
 * Ce qu'un arrêt dépose, commande par commande.
 *
 * Un arrêt est une adresse, pas une commande : le camion peut y déposer pour
 * trois clients à la fois. Replié, il ne montre qu'un lieu et un compteur —
 * déplié, il dit **pour qui** on s'arrête, ce qu'on décharge, et combien de
 * temps cela prend.
 *
 * Les grandeurs sont celles des services posés sur cet arrêt, pas de la commande
 * entière : une commande à moitié planifiée ailleurs n'apporte ici que ce qu'il
 * en reçoit.
 *
 * **Le retrait porte sur la commande, pas sur un service.** Le serveur étend de
 * toute façon le geste à tous les services de la commande dans cette tournée :
 * retirer la seule livraison laisserait le chargement au dépôt, un arrêt où le
 * camion charge ce que personne n'ira livrer.
 */
export function TourStopOrders({ orders, onUnplan }: TourStopOrdersProps) {
  const { t } = useTranslation()

  if (orders.length === 0) {
    return <p className="px-2 py-1 text-[11px] text-muted-foreground">{t('tours.noOrderHere')}</p>
  }

  return (
    <ul className="mt-2 flex flex-col gap-2 rounded-md border border-primary/30 bg-primary/5 p-2">
      {orders.map((order) => (
        <li key={order.id} className="rounded-md border bg-background px-2 py-1.5 shadow-sm">
          <div className="flex flex-wrap items-baseline justify-between gap-x-2">
            <Link
              to={`/orders/${order.id}`}
              className="text-xs font-medium text-primary hover:underline"
            >
              {order.orderNumber ?? order.id}
            </Link>
            <span className="flex items-center gap-2">
              {order.customerReference === null ? null : (
                <span className="text-[11px] text-muted-foreground">{order.customerReference}</span>
              )}

              {onUnplan === undefined || order.services.length === 0 ? null : (
                <button
                  type="button"
                  title={t('planning.unplanOrder')}
                  aria-label={t('planning.unplanOrderNamed', {
                    number: order.orderNumber ?? order.id,
                  })}
                  className="rounded p-0.5 text-muted-foreground transition-colors hover:text-destructive"
                  onClick={() => onUnplan(order.services.map((service) => service.id))}
                >
                  <X className="size-3.5" aria-hidden />
                </button>
              )}
            </span>
          </div>

          {/* Le destinataire : c'est chez lui que le camion s'arrete. */}
          <p className="truncate text-[11px] text-muted-foreground">
            {order.customerName ?? order.customerId}
          </p>

          <dl className="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-muted-foreground">
            <Figure icon={Package} label={t('tours.fields.packages')} value={order.packageCount} />
            <Figure
              icon={Weight}
              label={t('tours.fields.weightShort')}
              value={`${order.weight} kg`}
            />
            <Figure
              icon={Package}
              label={t('tours.fields.volume')}
              value={`${order.volume} m³`}
            />
            <Figure
              icon={Timer}
              label={t('tours.fields.serviceTime')}
              value={t('tours.minutes', { count: order.serviceMinutes })}
            />
          </dl>

          <ul className="mt-1 flex flex-wrap gap-1">
            {order.services.map((service) => (
              <li
                key={service.id}
                className="rounded border bg-background px-1.5 py-0.5 text-[11px]"
              >
                {service.name ?? service.serviceNumber}
                <span className="ml-1 text-muted-foreground">
                  {t('tours.minutes', { count: service.minutes })}
                </span>
              </li>
            ))}
          </ul>
        </li>
      ))}
    </ul>
  )
}

function Figure({
  icon: Icon,
  label,
  value,
}: {
  icon: typeof Package
  label: string
  value: string | number
}) {
  return (
    <div className="flex items-center gap-1">
      <Icon className="size-3" aria-hidden />
      <dt className="sr-only">{label}</dt>
      <dd>{value}</dd>
    </div>
  )
}
