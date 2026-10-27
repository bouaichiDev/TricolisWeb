import 'leaflet/dist/leaflet.css'

import L from 'leaflet'
import { useEffect } from 'react'
import { MapContainer, Marker, Popup, TileLayer, useMap } from 'react-leaflet'

import { formatDateTime } from '@/shared/utils/format'

import { ATTRIBUTION, TILE_URL, pinIcon, vehicleIcon } from './tiles'

export interface TrackingMapPoint {
  label: string
  latitude: number
  longitude: number
  completed: boolean
}

export interface TrackingMapPosition {
  latitude: number
  longitude: number
  recordedAt: string | null
}

interface TrackingMapProps {
  points: TrackingMapPoint[]
  position: TrackingMapPosition | null
  className?: string
}

const PENDING = pinIcon('text-primary')
const DONE = pinIcon('text-muted-foreground')
const VEHICLE = vehicleIcon()

/** Cadre la carte sur tout ce qu'elle montre. */
function FitBounds({ coordinates }: { coordinates: [number, number][] }) {
  const map = useMap()
  const key = JSON.stringify(coordinates)

  useEffect(() => {
    const parsed = JSON.parse(key) as [number, number][]
    if (parsed.length > 1) map.fitBounds(L.latLngBounds(parsed), { padding: [32, 32], maxZoom: 14 })
  }, [key, map])

  return null
}

/**
 * La carte du suivi : les adresses des prestations et, quand elle est connue,
 * la dernière position du véhicule — un point, jamais un historique.
 *
 * `referrerPolicy: no-referrer` sur les tuiles : sur la page publique, l'URL
 * porte le jeton du lien, et le fournisseur de tuiles n'a pas à la recevoir.
 */
export function TrackingMap({ points, position, className }: TrackingMapProps) {
  const coordinates: [number, number][] = [
    ...points.map((point): [number, number] => [point.latitude, point.longitude]),
    ...(position ? [[position.latitude, position.longitude] as [number, number]] : []),
  ]
  const center = coordinates.at(-1)

  if (center === undefined) return null

  return (
    <MapContainer center={center} zoom={13} scrollWheelZoom={false} className={className ?? 'h-72 w-full rounded-lg border'}>
      <TileLayer url={TILE_URL} attribution={ATTRIBUTION} referrerPolicy="no-referrer" />
      <FitBounds coordinates={coordinates} />

      {points.map((point, index) => (
        <Marker key={`${point.label}-${index}`} position={[point.latitude, point.longitude]} icon={point.completed ? DONE : PENDING}>
          <Popup>{point.label}</Popup>
        </Marker>
      ))}

      {position ? (
        <Marker position={[position.latitude, position.longitude]} icon={VEHICLE}>
          <Popup>Position du véhicule{position.recordedAt ? ` · ${formatDateTime(position.recordedAt)}` : ''}</Popup>
        </Marker>
      ) : null}
    </MapContainer>
  )
}
