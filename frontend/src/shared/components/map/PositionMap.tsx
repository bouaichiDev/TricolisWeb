import 'leaflet/dist/leaflet.css'

import L from 'leaflet'
import { MapContainer, Marker, Polyline, Popup, TileLayer } from 'react-leaflet'

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

/**
 * Fond de plan.
 *
 * OpenStreetMap par défaut, surchargeable par `VITE_MAP_TILE_URL`. Sa politique
 * d'usage interdit un trafic important sans hébergement propre : un déploiement
 * réel doit pointer vers son propre serveur de tuiles, et cette variable est là
 * pour ça.
 */
const TILE_URL =
  import.meta.env.VITE_MAP_TILE_URL ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'

const ATTRIBUTION =
  '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'

/**
 * Icône du marqueur, construite à la main.
 *
 * Leaflet devine sinon le chemin de ses images depuis celui de son CSS, ce qui
 * ne survit pas au regroupement des fichiers : le marqueur disparaît en
 * production sans la moindre erreur. Un SVG en ligne évite l'aller-retour et ne
 * dépend d'aucun fichier.
 */
const MARKER = L.divIcon({
  className: '',
  html: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" class="text-primary drop-shadow">
      <path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/>
    </svg>`,
  iconSize: [28, 28],
  iconAnchor: [14, 28],
  popupAnchor: [0, -28],
})

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
