import { useQuery } from '@tanstack/react-query'
import { History } from 'lucide-react'

import { errorMessage } from '@/shared/api/errors'
import { EmptyState, ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { LazyTrackingMap } from '@/shared/components/map/LazyTrackingMap'
import { formatDateTime } from '@/shared/utils/format'

import { toursApi } from '../api/toursApi'
import type { TourStop } from '../types'

const DONE = ['completed', 'skipped', 'cancelled']

/**
 * Ce qui s'est réellement passé : horodatages de la tournée et de ses arrêts,
 * événements de suivi. La carte montre les arrêts géolocalisés et la dernière
 * position réellement enregistrée — rien d'inventé.
 */
export function TourTimeline({ tourId, stops }: { tourId: string; stops: TourStop[] }) {
  const timeline = useQuery({ queryKey: ['tour', tourId, 'tracking'], queryFn: () => toursApi.tracking(tourId) })

  if (timeline.isPending) return <InlineLoader />
  if (timeline.isError) return <ErrorState message={errorMessage(timeline.error)} onRetry={() => void timeline.refetch()} />

  const points = stops.flatMap((stop) =>
    stop.address?.latitude != null && stop.address.longitude != null
      ? [{ label: `${stop.sequence}. ${stop.address.name ?? 'Arrêt'}`, latitude: stop.address.latitude, longitude: stop.address.longitude, completed: DONE.includes(stop.status) }]
      : [],
  )
  const located = timeline.data.filter((entry) => entry.latitude !== null && entry.longitude !== null).at(-1)
  const position = located ? { latitude: located.latitude as number, longitude: located.longitude as number, recordedAt: located.occurredAt } : null

  return (
    <div className="flex flex-col gap-4">
      {(points.length > 0 || position) && <LazyTrackingMap points={points} position={position} />}
      <SectionCard title="Chronologie">
        {timeline.data.length === 0 ? (
          <EmptyState title="Rien n’a encore été enregistré" icon={History} />
        ) : (
          <ol className="space-y-3" aria-label="Chronologie">
            {timeline.data.map((entry, index) => (
              <li key={`${entry.type}-${entry.occurredAt}-${index}`} className="flex flex-wrap items-baseline justify-between gap-2 border-b pb-2 text-sm last:border-0">
                <span>
                  <span className="font-medium">{entry.label}</span>
                  {entry.stopSequence !== null && <span className="text-muted-foreground"> · arrêt {entry.stopSequence}</span>}
                  {entry.serviceNumber && <span className="text-muted-foreground"> · {entry.serviceNumber}</span>}
                </span>
                <time className="text-muted-foreground" dateTime={entry.occurredAt}>{formatDateTime(entry.occurredAt)}</time>
              </li>
            ))}
          </ol>
        )}
      </SectionCard>
    </div>
  )
}
