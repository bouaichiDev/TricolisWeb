import 'leaflet/dist/leaflet.css'

import { MapContainer, Marker, Polyline, Popup, TileLayer } from 'react-leaflet'

import { ATTRIBUTION, TILE_URL, pinIcon } from './tiles'

export interface MapPoint {
  latitude: number
  longitude: number
  label?: string
}

interface PositionMapProps {
  /** Du plus ancien au plus récent ; le dernier porte le marqueur. */
  points: MapPoint[]
  className?: string
}

const MARKER = pinIcon('text-primary')

/**
 * Une carte, et rien d'autre.
 *
 * Volontairement dans `shared` et sans notion de véhicule ni de commande : le
 * suivi d'un chauffeur n'est que son premier usage, et un composant qui
 * connaîtrait la télématique ne servirait qu'à elle.
 *
 * Le tracé relie les positions dans l'ordre où elles ont été relevées ; le
 * marqueur est sur la dernière. Une carte centrée sans marqueur laisserait
 * chercher où regarder.
 */
export function PositionMap({ points, className }: PositionMapProps) {
  const last = points.at(-1)

  if (last === undefined) return null

  const path: [number, number][] = points.map((point) => [point.latitude, point.longitude])

  return (
    <MapContainer
      center={[last.latitude, last.longitude]}
      zoom={13}
      scrollWheelZoom={false}
      className={className ?? 'h-64 w-full rounded-lg border'}
    >
      <TileLayer url={TILE_URL} attribution={ATTRIBUTION} />

      {path.length > 1 ? <Polyline positions={path} className="text-primary" /> : null}

      <Marker position={[last.latitude, last.longitude]} icon={MARKER}>
        {last.label === undefined ? null : <Popup>{last.label}</Popup>}
      </Marker>
    </MapContainer>
  )
}
