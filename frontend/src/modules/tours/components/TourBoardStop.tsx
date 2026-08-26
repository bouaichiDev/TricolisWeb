import { Clock, MapPin, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { StatusBadge } from '@/shared/components/data/StatusBadge'

import type { TourStop } from '../types/tour'

interface TourBoardStopProps {
  stop: TourStop
  /** Absent quand la tournée ne laisse rien retirer. */
  onUnplan?: (orderServiceIds: string[]) => void
}

/**
 * Un arrêt dans une colonne : son rang, son adresse, ce qu'il porte.
 *
 * Les commandes y sont des liens : sans eux, il faudrait deviner ce que le
 * camion vient faire là.
 */
export function TourBoardStop({ stop, onUnplan }: TourBoardStopProps) {
  const { t } = useTranslation()

  const serviceIds = stop.orderServiceIds ?? []
  const orders = stop.orders ?? []

  return (
    <div className="flex items-start gap-2 rounded-md border px-2 py-1.5">
      <span className="flex size-6 shrink-0 items-center justify-center rounded border text-xs font-medium">
        {stop.sequence}
      </span>

      <span className="min-w-0 flex-1">
        <span className="flex items-center gap-1 truncate text-xs">
          <MapPin className="size-3 shrink-0 text-muted-foreground" aria-hidden />
          {stop.addressLabel ?? stop.addressId}
        </span>

        <span className="flex items-center gap-2 text-[11px] text-muted-foreground">
          <StatusBadge status={stop.status} source="tour_stop" />
          {stop.serviceCount === undefined ? null : (
            <span className="flex items-center gap-1">
              <Clock className="size-3" aria-hidden />
              {t('tours.serviceCount', { count: stop.serviceCount })}
            </span>
          )}
        </span>

        {orders.length === 0 ? null : (
          <span className="flex flex-wrap gap-x-2 text-[11px]">
            {orders.map((order) => (
              <Link
                key={order.id}
                to={`/orders/${order.id}`}
                className="text-primary hover:underline"
              >
                {order.orderNumber ?? order.id}
              </Link>
            ))}
          </span>
        )}
      </span>

      {onUnplan !== undefined && serviceIds.length > 0 ? (
        <button
          type="button"
          title={t('planning.unplanStop')}
          aria-label={t('planning.unplanStop')}
          className="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:text-destructive"
          onClick={() => onUnplan(serviceIds)}
        >
          <X className="size-3.5" aria-hidden />
        </button>
      ) : null}
    </div>
  )
}
