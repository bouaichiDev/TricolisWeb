import { CheckCircle2, CircleDashed } from 'lucide-react'

import { cn } from '@/shared/utils/cn'
import { formatDateTime } from '@/shared/utils/format'

import type { TimelineItem } from '../types'

/**
 * La timeline du client : des faits datés, et ce qui est prévu, distingué
 * visuellement — un passage planifié n'est pas un passage effectué.
 */
export function TrackingTimeline({ items }: { items: TimelineItem[] }) {
  if (items.length === 0) {
    return <p className="text-muted-foreground text-sm">Aucun événement pour le moment.</p>
  }

  return (
    <ol className="flex flex-col" aria-label="Timeline de suivi">
      {items.map((item, index) => {
        const expected = item.state === 'expected'
        const Icon = expected ? CircleDashed : CheckCircle2

        return (
          <li key={`${item.type}-${item.at}-${index}`} className="relative flex gap-3 pb-4 last:pb-0">
            {index < items.length - 1 && <span className="bg-border absolute top-6 left-[9px] h-[calc(100%-1.25rem)] w-px" aria-hidden />}
            <Icon className={cn('mt-0.5 size-5 shrink-0', expected ? 'text-muted-foreground' : 'text-primary')} aria-hidden />
            <div className="min-w-0">
              <p className={cn('text-sm font-medium', expected && 'text-muted-foreground')}>{item.title}</p>
              {item.description && <p className="text-muted-foreground text-sm">{item.description}</p>}
              <p className="text-muted-foreground text-xs">
                {expected ? 'Prévu · ' : ''}
                {formatDateTime(item.at)}
              </p>
            </div>
          </li>
        )
      })}
    </ol>
  )
}
