import { Check, MapPin } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { cn } from '@/shared/utils/cn'
import { formatDateTime } from '@/shared/utils/format'

import type { JourneyStep } from '../types/trackingDefinition'

interface JourneyTimelineProps {
  steps: JourneyStep[]
}

/**
 * Le parcours d'une commande, du départ à l'arrivée.
 *
 * Toutes les étapes configurées sont montrées **dès le début** — créé · chargé ·
 * en route · livré — franchies ou non. Une liste qui s'allonge dirait où on en
 * est sans jamais dire ce qui reste, et c'est précisément la question du client.
 *
 * L'étape franchie porte sa date ; celle à venir reste pâle. La première étape
 * non franchie est marquée comme *en cours* : c'est là qu'en est la commande.
 */
export function JourneyTimeline({ steps }: JourneyTimelineProps) {
  const { t } = useTranslation()

  const currentIndex = steps.findIndex((step) => step.occurredAt === null)

  return (
    <ol className="flex flex-col">
      {steps.map((step, index) => {
        const done = step.occurredAt !== null
        const current = index === currentIndex
        const last = index === steps.length - 1

        return (
          <li key={step.definition.id} className="relative flex gap-3 pb-5 last:pb-0">
            {last ? null : (
              <span
                className={cn(
                  'absolute left-[11px] top-6 h-full w-px',
                  done ? 'bg-primary' : 'bg-border',
                )}
                aria-hidden
              />
            )}

            <span
              className={cn(
                'relative mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border-2',
                done
                  ? 'border-primary bg-primary text-primary-foreground'
                  : current
                    ? 'border-primary bg-background'
                    : 'border-border bg-background',
              )}
            >
              {done ? <Check className="size-3.5" aria-hidden /> : null}
            </span>

            <div className={cn('min-w-0 flex-1', !done && !current && 'opacity-60')}>
              <p className="flex flex-wrap items-center gap-2 font-medium">
                {step.definition.title}

                {step.definition.isLive ? (
                  <span className="flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[11px] font-normal text-muted-foreground">
                    <MapPin className="size-3" aria-hidden />
                    {t('tracking.liveStep')}
                  </span>
                ) : null}
              </p>

              <p className="text-xs text-muted-foreground">
                {done
                  ? formatDateTime(step.occurredAt)
                  : current
                    ? t('tracking.pending')
                    : t('tracking.upcoming')}
              </p>

              {step.description ? (
                <p className="mt-1 text-sm text-muted-foreground">{step.description}</p>
              ) : null}
            </div>
          </li>
        )
      })}
    </ol>
  )
}
