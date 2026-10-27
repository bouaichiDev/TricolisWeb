import type { StatusView } from '@/modules/orders/types'
import { StatusBadge } from '@/shared/components/data/StatusBadge'

import type { CalendarOrderEvent } from '../types'

/**
 * Légende des statuts présents sur la période affichée — et seulement
 * ceux-là : une légende de dix teintes dont trois servent n'aide personne.
 */
export function CalendarStatusLegend({ events }: { events: CalendarOrderEvent[] }) {
  const statuses = new Map<string, StatusView>()

  events.forEach(({ extendedProps: { status } }) => {
    if (status.code && !statuses.has(status.code)) statuses.set(status.code, status)
  })

  if (statuses.size === 0) return null

  return (
    <div className="flex flex-wrap items-center gap-2" aria-label="Légende des statuts">
      <span className="text-muted-foreground text-sm">Statuts :</span>
      {[...statuses.values()].map((status) => (
        <StatusBadge key={status.code} code={status.code} label={status.label} color={status.color} />
      ))}
    </div>
  )
}
