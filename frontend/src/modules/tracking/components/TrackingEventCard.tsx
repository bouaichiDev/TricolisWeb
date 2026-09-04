import { MapPin } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { hasCoordinates, type TrackingEvent } from '../types/trackingEvent'

interface TrackingEventCardProps {
  event: TrackingEvent
  onOpen: () => void
}

/**
 * Un événement de suivi, dans la timeline.
 *
 * `eventType` est affiché **tel que le serveur le renvoie**. Le champ est une
 * chaîne libre : le traduire par une table de correspondance côté React ferait
 * disparaître de l'écran tout type que le métier ajouterait ensuite.
 */
export function TrackingEventCard({ event, onOpen }: TrackingEventCardProps) {
  const { t } = useTranslation()

  return (
    <li className="relative flex gap-3 pb-4 last:pb-0">
      {/* Le trait vertical relie les evenements ; le dernier ne le prolonge pas. */}
      <span
        className="absolute left-[7px] top-4 h-full w-px bg-border last:hidden"
        aria-hidden
      />
      <span className="relative mt-1.5 size-3.5 shrink-0 rounded-full border-2 border-primary bg-background" />

      <div className="min-w-0 flex-1 rounded-lg border bg-card p-3">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="min-w-0">
            <p className="truncate font-medium">{event.eventType}</p>
            <p className="text-xs text-muted-foreground">{formatDateTime(event.occurredAt)}</p>
          </div>

          <StatusBadge status={event.status} />
        </div>

        {event.description ? (
          <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">{event.description}</p>
        ) : null}

        <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
          <span className="text-xs text-muted-foreground">
            {hasCoordinates(event) ? (
              <span className="flex items-center gap-1">
                <MapPin className="size-3" aria-hidden />
                {event.latitude}, {event.longitude}
              </span>
            ) : null}
          </span>

          <Button type="button" variant="ghost" size="sm" onClick={onOpen}>
            {t('tracking.openDetail')}
          </Button>
        </div>
      </div>
    </li>
  )
}
