import { LazyTrackingMap } from '@/shared/components/map/LazyTrackingMap'
import { formatDateTime } from '@/shared/utils/format'

import type { MapPoint, Position } from '../types'

interface TrackingMapSectionProps {
  points: MapPoint[]
  position: Position | null
}

/**
 * La carte n'apparaît que si une donnée la justifie (§15) : adresse géocodée
 * ou position du véhicule. Sans position, la ligne le dit plutôt que de
 * laisser croire que le véhicule est à l'arrêt.
 */
export function TrackingMapSection({ points, position }: TrackingMapSectionProps) {
  const hasMap = points.length > 0 || position !== null

  return (
    <div className="flex flex-col gap-2">
      {hasMap ? <LazyTrackingMap points={points} position={position} /> : <p className="text-muted-foreground text-sm">Carte indisponible : aucune adresse localisée.</p>}
      <p className="text-muted-foreground text-sm" data-testid="position-status">
        {position
          ? `Position du véhicule${position.recordedAt ? ` relevée le ${formatDateTime(position.recordedAt)}` : ''}`
          : 'Position indisponible'}
      </p>
    </div>
  )
}
