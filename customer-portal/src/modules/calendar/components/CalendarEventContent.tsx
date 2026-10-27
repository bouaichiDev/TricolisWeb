import { statusAppearance } from '@/shared/components/data/statusTones'
import { cn } from '@/shared/utils/cn'

import type { CalendarOrderEvent } from '../types'

interface CalendarEventContentProps {
  event: CalendarOrderEvent
  timeText: string
}

/**
 * Un événement du calendrier, teinté par le statut de la commande : l'état
 * se lit sans ouvrir la vue rapide. La couleur est celle du référentiel des
 * statuts, ou la teinte de repli du badge — la même partout dans le portail.
 */
export function CalendarEventContent({ event, timeText }: CalendarEventContentProps) {
  const status = event.extendedProps.status
  const appearance = statusAppearance(status.code, status.color)

  return (
    <div
      className={cn('flex w-full min-w-0 flex-col overflow-hidden rounded border px-1.5 py-0.5 text-xs leading-tight', appearance.className)}
      style={appearance.style}
      title={`${event.title} · ${status.label ?? status.code ?? ''}`}
      data-status={status.code ?? ''}
    >
      <span className="truncate font-medium">
        {timeText && <span className="mr-1 font-normal opacity-80">{timeText}</span>}
        {event.title}
      </span>
      {status.label && <span className="truncate opacity-80">{status.label}</span>}
    </div>
  )
}
