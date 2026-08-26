import 'leaflet/dist/leaflet.css'

import { useTranslation } from 'react-i18next'
import { MapContainer, Marker, Polyline, Popup, TileLayer } from 'react-leaflet'

import type { Tour } from '@/modules/tours/types/tour'
import { ATTRIBUTION, TILE_URL, pinIcon, sequenceIcon } from '@/shared/components/map/tiles'

import { isDeparture, poolPoints, stopPoints, unplottableCount } from '../points'
import type { PoolOrder } from '../types/pool'

interface PlanningMapProps {
  orders: PoolOrder[]
  /** Les brouillons dont on trace les arrêts ; vide, la carte ne montre que le pool. */
  tours: Tour[]
  onPlanOrder?: (orderId: string) => void
}

const WAITING = pinIcon('text-amber-500')

/**
 * La planification sur fond de carte.
 *
 * Elle répond à une question que les colonnes ne savent pas poser : ces deux
 * commandes sont-elles voisines ? Le §73 impose qu'elle travaille sur les
 * **mêmes brouillons** que l'écran en colonnes — c'est une autre lecture, pas un
 * autre état.
 *
 * Une adresse non géocodée n'apparaît pas : elle reste planifiable, et leur
 * nombre est annoncé sous la carte plutôt que passé sous silence.
 */
export function PlanningMap({ orders, tours, onPlanOrder }: PlanningMapProps) {
  const { t } = useTranslation()

  const waiting = poolPoints(orders)
  const missing = unplottableCount(orders)
  const routes = tours.map((tour) => ({ tour, stops: stopPoints(tour) }))

  // Le premier point connu fait le centre : commencer au milieu de l'ocean
  // obligerait a chercher ou sont les commandes.
  const anchor = waiting[0] ?? routes.flatMap((route) => route.stops)[0]

  if (anchor === undefined) {
    return (
      <div className="rounded-lg border border-dashed p-12 text-center">
        <p className="font-medium">{t('planning.mapEmpty')}</p>
        <p className="mt-1 text-sm text-muted-foreground">{t('planning.mapEmptyHint')}</p>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-2">
      <MapContainer
        center={[anchor.latitude, anchor.longitude]}
        zoom={11}
        scrollWheelZoom
        className="h-[32rem] w-full rounded-lg border"
      >
        <TileLayer url={TILE_URL} attribution={ATTRIBUTION} />

        {routes.map(({ tour, stops }) => (
          <div key={tour.id}>
            {stops.length > 1 ? (
              <Polyline
                positions={stops.map((stop) => [stop.latitude, stop.longitude])}
                className="text-primary"
              />
            ) : null}

            {stops.map((stop) => (
              <Marker
                key={stop.id}
                position={[stop.latitude, stop.longitude]}
                icon={sequenceIcon(
                  stop.sequence,
                  isDeparture(tour, stop) ? 'bg-emerald-600' : 'bg-primary',
                )}
              >
                <Popup>
                  <span className="block font-medium">{tour.tourNumber}</span>
                  <span className="block">
                    {isDeparture(tour, stop)
                      ? t('planning.departure')
                      : (stop.addressLabel ?? stop.addressId)}
                  </span>
                </Popup>
              </Marker>
            ))}
          </div>
        ))}

        {waiting.map((point) => (
          <Marker key={point.key} position={[point.latitude, point.longitude]} icon={WAITING}>
            <Popup>
              <span className="block font-medium">{point.label}</span>
              {/* Une adresse peut réunir plusieurs commandes : chacune se
                  planifie séparément, sinon le clic en emporterait d'autres. */}
              <ul className="mt-1 flex flex-col gap-1">
                {point.orders.map((order) => (
                  <li key={order.id} className="flex items-center gap-2">
                    <span>{order.orderNumber}</span>
                    {onPlanOrder === undefined ? null : (
                      <button
                        type="button"
                        className="underline"
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
        ))}
      </MapContainer>

      {missing > 0 ? (
        <p className="text-xs text-muted-foreground">
          {t('planning.notGeocoded', { count: missing })}
        </p>
      ) : null}
    </div>
  )
}
