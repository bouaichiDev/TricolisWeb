import 'leaflet/dist/leaflet.css'

import { useTranslation } from 'react-i18next'
import { MapContainer, Marker, Polyline, Popup, TileLayer } from 'react-leaflet'
import { Link } from 'react-router-dom'

import type { Tour } from '@/modules/tours/types/tour'
import { ATTRIBUTION, TILE_URL, sequenceIcon } from '@/shared/components/map/tiles'

import { MapFocus, type MapTarget } from './map/MapFocus'
import { PoolMarker } from './map/PoolMarker'
import { isDeparture, poolPoints, stopPoints, tourColor, unplottableCount } from '../points'
import type { PoolOrder } from '../types/pool'

interface PlanningMapProps {
  orders: PoolOrder[]
  /** Toutes les tournées du filtre, pas seulement les brouillons. */
  tours: Tour[]
  onPlanOrder?: (orderId: string) => void
  /**
   * Tracé routier de la tournée sélectionnée, en `[latitude, longitude]`.
   *
   * Vide quand aucun fournisseur de géométrie n'est déclaré : les segments à
   * vol d'oiseau prennent le relais, et la légende le dit.
   */
  routeTrace?: [number, number][]
  /** Tournée à laquelle ce tracé appartient. */
  routeTourId?: string | null
  /** Point à rejoindre, désigné depuis une liste. */
  focus?: MapTarget | null
  /**
   * Hauteur du fond de plan.
   *
   * **Une hauteur explicite, jamais `flex-1` par défaut.** Leaflet mesure son
   * conteneur au montage : dans un parent de hauteur automatique — une fenêtre,
   * par exemple — un `flex-1` vaut zéro pixel, et la carte se monte sans
   * jamais s'afficher. L'appelant qui dispose d'une hauteur, lui, peut la
   * passer.
   */
  className?: string
}

/** Le départ au dépôt garde sa teinte propre, quelle que soit la tournée. */
const DEPARTURE_COLOR = '#059669'

/**
 * La planification sur fond de carte.
 *
 * Elle répond à une question que les colonnes ne savent pas poser : ces deux
 * commandes sont-elles voisines ? Le §73 impose qu'elle travaille sur les
 * **mêmes** brouillons que l'écran en colonnes.
 *
 * **Chaque tournée y montre ce qu'elle porte déjà**, brouillon ou non : une
 * tournée confirmée occupe le terrain, et planifier sans la voir reviendrait à
 * envoyer deux camions dans la même rue.
 *
 * **Trait plein = route réelle, pointillé = vol d'oiseau.** Le service de
 * routage du projet rend `Distance` et `TravelTime`, mais aucune géométrie :
 * rien n'y décrit le chemin suivi. Un second fournisseur, déclaré à part, sait
 * le dessiner — le §101 l'autorise expressément. Quand il répond, la tournée
 * sélectionnée porte son vrai tracé ; sinon les segments reviennent, en
 * pointillé, et la légende dit lequel des deux on regarde. Jamais les deux à la
 * fois : superposés, on ne saurait plus lequel dit la vérité.
 *
 * **On ne glisse pas ici.** Sur un fond de plan, lâcher « sur une tournée » ne
 * veut rien dire : une tournée n'y est pas une zone mais une ligne brisée.
 */
export function PlanningMap({
  orders,
  tours,
  onPlanOrder,
  routeTrace,
  routeTourId,
  focus,
  className,
}: PlanningMapProps) {
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
      <div className="flex min-h-64 flex-col items-center justify-center rounded-lg border border-dashed p-12 text-center">
        <p className="font-medium">{t('planning.mapEmpty')}</p>
        <p className="mt-1 text-sm text-muted-foreground">{t('planning.mapEmptyHint')}</p>
      </div>
    )
  }

  return (
    <div className="flex h-full min-h-0 flex-col gap-2">
      <MapContainer
        center={[anchor.latitude, anchor.longitude]}
        zoom={11}
        scrollWheelZoom
        className={className ?? 'h-[28rem] w-full rounded-lg border'}
      >
        <TileLayer url={TILE_URL} attribution={ATTRIBUTION} />
        <MapFocus target={focus ?? null} />

        {routes.map(({ tour, stops, color }) => (
          <div key={tour.id}>
            {/* Le trace routier quand on l'a, les segments a vol d'oiseau
                sinon. Jamais les deux : superposes, on ne saurait plus lequel
                dit la verite. */}
            {tour.id === routeTourId && (routeTrace ?? []).length > 1 ? (
              <Polyline positions={routeTrace ?? []} pathOptions={{ color, weight: 4 }} />
            ) : stops.length > 1 ? (
              <Polyline
                positions={stops.map((stop) => [stop.latitude, stop.longitude])}
                pathOptions={{ color, dashArray: '6 8', weight: 3 }}
              />
            ) : null}

            {stops.map((stop) => (
              <Marker
                key={stop.id}
                position={[stop.latitude, stop.longitude]}
                icon={sequenceIcon(stop.sequence, isDeparture(tour, stop) ? DEPARTURE_COLOR : color)}
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

                  <ul className="mt-1 flex flex-col gap-0.5">
                    {(stop.orders ?? []).map((order) => (
                      <li key={order.id}>
                        <Link to={`/orders/${order.id}`} className="underline">
                          {order.orderNumber ?? order.id}
                        </Link>
                        {order.customerName === null ? null : ` · ${order.customerName}`}
                      </li>
                    ))}
                  </ul>
                </Popup>
              </Marker>
            ))}
          </div>
        ))}

        {waiting.map((point) => (
          <PoolMarker key={point.key} point={point} onPlanOrder={onPlanOrder} />
        ))}
      </MapContainer>

      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
        {routes.map(({ tour, stops, color }) => (
          <span key={tour.id} className="flex items-center gap-1.5">
            <span aria-hidden className="h-0.5 w-5" style={{ backgroundColor: color }} />
            <span className="font-medium">{tour.tourNumber}</span>
            <span className="text-muted-foreground">
              {t('planning.stopCount', { count: stops.length })}
            </span>
          </span>
        ))}

        <span className="text-muted-foreground">
          {(routeTrace ?? []).length > 1 ? t('planning.realRoute') : t('planning.straightLines')}
        </span>

        {missing > 0 ? (
          <span className="text-muted-foreground">{t('planning.notGeocoded', { count: missing })}</span>
        ) : null}
      </div>
    </div>
  )
}
