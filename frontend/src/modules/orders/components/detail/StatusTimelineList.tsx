import { useTranslation } from 'react-i18next'

import type { Status } from '@/modules/statuses/types/status'
import { Badge } from '@/shared/components/ui/badge'
import { cn } from '@/shared/utils/cn'
import { formatDateTime } from '@/shared/utils/format'

import type { TimelineEntry } from '../../schemas/statusTimeline'

interface StatusTimelineListProps {
  entries: TimelineEntry[]
  known: Map<string, Status>
}

/**
 * Le parcours de statuts, du plus ancien au possible.
 *
 * Le passé est daté et coloré, la suite est grise et sans date : la différence
 * doit se voir sans lire, sinon une possibilité se prend pour un fait.
 */
export function StatusTimelineList({ entries, known }: StatusTimelineListProps) {
  const { t } = useTranslation()

  return (
    <ol className="flex flex-col gap-4">
      {entries.map((entry, index) => (
        <li key={`${entry.code}-${index}`} className="flex gap-3">
          <span className="flex justify-center pt-1.5">
            <span
              className={cn(
                'size-2.5 rounded-full',
                entry.reached ? 'bg-primary ring-4 ring-primary/15' : 'bg-muted-foreground/30',
              )}
              aria-hidden
            />
          </span>

          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant={entry.reached ? 'secondary' : 'outline'}>
                {entry.label || t(`auditActions.${entry.code}`, { defaultValue: entry.code })}
              </Badge>
              <span className="font-mono text-sm text-muted-foreground">
                {entry.date === null ? '—' : formatDateTime(entry.date)}
              </span>
            </div>

            <p className="mt-0.5 text-sm text-muted-foreground">
              {!entry.reached
                ? t('orders.statusTimeline.upcoming')
                : entry.detail === 'created'
                  ? t('orders.statusTimeline.created')
                  : entry.detail === null
                    ? t('orders.statusTimeline.reached')
                    : t('orders.statusTimeline.from', {
                        status: known.get(entry.detail)?.label ?? entry.detail,
                      })}
            </p>
          </div>
        </li>
      ))}
    </ol>
  )
}
