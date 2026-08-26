import 'leaflet/dist/leaflet.css'

import { useTranslation } from 'react-i18next'
import { MapContainer, Marker, Polyline, Popup, TileLayer } from 'react-leaflet'
import { Link } from 'react-router-dom'

import type { Tour } from '@/modules/tours/types/tour'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ATTRIBUTION, TILE_URL, pinIcon, sequenceIcon } from '@/shared/components/map/tiles'

import { isDeparture, poolPoints, stopPoints, tourColor, unplottableCount } from '../points'
import type { PoolOrder } from '../types/pool'

interface PlanningMapProps {
  orders: PoolOrder[]
  /** Toutes les tournées du filtre, pas seulement les brouillons. */
  tours: Tour[]
  onPlanOrder?: (orderId: string) => void
}

const WAITING = pinIcon('text-amber-500')

/** Le départ au dépôt garde sa teinte propre, quelle que soit la tournée. */
const DEPARTURE_COLOR = '#059669'

/**
 * La planification sur fond de carte.
 *
 * Elle répond à une question que les colonnes ne savent pas poser : ces deux
 * commandes sont-elles voisines ? Le §73 impose qu'elle travaille sur les
 * **mêmes** brouillons que l'écran en colonnes — c'est une autre lecture, pas
 * un autre état.
 *
 * **Chaque tournée y montre ce qu'elle porte déjà**, brouillon ou non : une
 * tournée confirmée occupe le terrain, et planifier sans la voir reviendrait à
 * envoyer deux camions dans la même rue. Sa couleur la distingue, ses arrêts
 * sont numérotés dans l'ordre.
 *
 * **On ne glisse pas ici.** Sur un fond de plan, lâcher « sur une tournée » ne
 * veut rien dire : une tournée n'y est pas une zone mais une ligne brisée. Le
 * glisser vit dans la vue en panneaux ; ici on planifie depuis la bulle d'un
 * marqueur.
 *
 * La bulle d'un arrêt mène aux commandes qui l'ont fait exister : c'est de là
 * qu'on vérifie un contenu sans quitter la carte.
 *
 * Une adresse non géocodée n'apparaît pas : elle reste planifiable, et leur
 * nombre est annoncé sous la carte plutôt que passé sous silence.
 */
export function PlanningMap({ orders, tours, onPlanOrder }: PlanningMapProps) {
  const { t } = useTranslation()

  const waiting = poolPoints(orders)
  const missing = unplottableCount(orders)

  const routes = tours
    .map((tour, index) => ({ tour, stops: stopPoints(tour), color: tourColor(index) }))
    .filter((route) => route.stops.length > 0)

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

        {routes.map(({ tour, stops, color }) => (
          <div key={tour.id}>
            {stops.length > 1 ? (
              <Polyline
                positions={stops.map((stop) => [stop.latitude, stop.longitude])}
                pathOptions={{ color }}
              />
            ) : null}

            {stops.map((stop) => (
              <Marker
                key={stop.id}
                position={[stop.latitude, stop.longitude]}
                icon={sequenceIcon(
                  stop.sequence,
                  isDeparture(tour, stop) ? DEPARTURE_COLOR : color,
                )}
              >
                <Popup>
                  <span className="block font-medium">{tour.tourNumber}</span>
                  <span className="block">
                    {isDeparture(tour, stop)
                      ? t('planning.departure')
                      : (stop.addressLabel ?? stop.addressId)}
                  </span>
                  <span className="mt-1 block">
                    {t('tours.serviceCount', { count: stop.serviceCount ?? 0 })}
                  </span>

                  {/* Depuis l'arret, remonter a ce qui l'a fait exister : le
                      planificateur y verifie le contenu sans quitter la carte. */}
                  {(stop.orders ?? []).length === 0 ? null : (
                    <ul className="mt-1 flex flex-col gap-0.5">
                      {(stop.orders ?? []).map((order) => (
                        <li key={order.id}>
                          <Link to={`/orders/${order.id}`} className="underline">
                            {order.orderNumber ?? order.id}
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
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

      {routes.length === 0 ? null : (
        <ul className="flex flex-wrap items-center gap-3 text-xs">
          {routes.map(({ tour, stops, color }) => (
            <li key={tour.id} className="flex items-center gap-1.5">
              <span
                aria-hidden
                className="size-3 rounded-full"
                style={{ backgroundColor: color }}
              />
              <span className="font-medium">{tour.tourNumber}</span>
              <StatusBadge status={tour.status} source="tour" />
              <span className="text-muted-foreground">
                {t('planning.stopCount', { count: stops.length })}
              </span>
            </li>
          ))}
        </ul>
      )}

      {missing > 0 ? (
        <p className="text-xs text-muted-foreground">
          {t('planning.notGeocoded', { count: missing })}
        </p>
      ) : null}
    </div>
  )
}
