import { useTranslation } from 'react-i18next'
import { Marker, Popup } from 'react-leaflet'
import { Link } from 'react-router-dom'

import { pinIcon } from '@/shared/components/map/tiles'

import type { PlanningPoint } from '../../points'

const WAITING = pinIcon('text-amber-500')

/**
 * Une adresse qui attend une tournée.
 *
 * Une même adresse peut réunir plusieurs commandes : chacune se planifie
 * séparément, sinon le clic en emporterait d'autres sans le dire.
 */
export function PoolMarker({
  point,
  onPlanOrder,
}: {
  point: PlanningPoint
  onPlanOrder?: (orderId: string) => void
}) {
  const { t } = useTranslation()

  return (
    <Marker position={[point.latitude, point.longitude]} icon={WAITING}>
      <Popup>
        <span className="block font-medium">{point.label}</span>

        <ul className="mt-1 flex flex-col gap-1">
          {point.orders.map((order) => (
            <li key={order.id} className="flex flex-col gap-0.5 border-t pt-1 first:border-0">
              <Link to={`/orders/${order.id}`} className="font-medium underline">
                {order.orderNumber}
              </Link>
              <span>{order.summary}</span>

              {onPlanOrder === undefined ? null : (
                <button
                  type="button"
                  className="self-start rounded bg-primary px-2 py-0.5 font-medium text-primary-foreground"
                  onClick={() => onPlanOrder(order.id)}
                >
                  {t('planning.planOrder')}
                </button>
              )}
            </li>
          ))}
        </ul>
      </Popup>
    </Marker>
  )
}
