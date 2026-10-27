import { lazy, Suspense } from 'react'

import type { TrackingMapPoint, TrackingMapPosition } from './TrackingMap'

/**
 * La carte, chargée seulement quand elle s'affiche : Leaflet pèse environ
 * 260 ko, que les écrans sans carte n'ont pas à payer.
 */
const TrackingMap = lazy(() => import('./TrackingMap').then((module) => ({ default: module.TrackingMap })))

interface LazyTrackingMapProps {
  points: TrackingMapPoint[]
  position: TrackingMapPosition | null
  className?: string
}

export function LazyTrackingMap(props: LazyTrackingMapProps) {
  return (
    <Suspense
      fallback={
        <div className="text-muted-foreground flex h-72 w-full items-center justify-center rounded-lg border text-sm">Chargement de la carte…</div>
      }
    >
      <TrackingMap {...props} />
    </Suspense>
  )
}
