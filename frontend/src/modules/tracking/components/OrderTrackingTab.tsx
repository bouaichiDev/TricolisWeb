import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { Button } from '@/shared/components/ui/button'

import { JourneyTimeline } from './JourneyTimeline'
import { NewTrackingEventDialog } from './NewTrackingEventDialog'
import { TrackingEventCard } from './TrackingEventCard'
import { TrackingEventDetailDrawer } from './TrackingEventDetailDrawer'
import { useOrderTracking, useTrackingDefinitions } from '../hooks/useTracking'
import { buildJourney, looseEvents } from '../schemas/journey'
import type { TrackingEvent } from '../types/trackingEvent'

interface OrderTrackingTabProps {
  orderId: string
  /** Faux tant que l'onglet n'a pas été ouvert : les requêtes attendent. */
  active: boolean
}

/**
 * Suivi d'une commande, en parcours plutôt qu'en journal.
 *
 * Le chauffeur pose un statut, l'étape apparaît : les événements ne se saisissent
 * pas, ils sont publiés par les changements de statut que l'organisation a
 * décrits dans son parcours.
 *
 * **Toutes les étapes sont montrées dès le début**, franchies ou non. Une liste
 * qui s'allonge dit où on en est sans jamais dire ce qui reste — et c'est
 * précisément ce que le client demande.
 *
 * Sans parcours configuré, l'écran retombe sur le journal brut et le dit :
 * mieux vaut des événements sans mise en scène qu'un écran vide.
 *
 * Rien ne se modifie ni ne s'efface : `tracking-events` n'expose ni `update` ni
 * `destroy`. Un journal se complète, il ne se corrige pas.
 */
export function OrderTrackingTab({ orderId, active }: OrderTrackingTabProps) {
  const { t } = useTranslation()
  const [opened, setOpened] = useState<TrackingEvent | null>(null)
  const [creating, setCreating] = useState(false)

  const definitions = useTrackingDefinitions(active)
  const { data, isPending, error, refetch } = useOrderTracking(
    orderId,
    { page: 1, perPage: 100, sort: 'occurred_at', direction: 'asc' },
    active,
  )

  const events = data?.data ?? []
  const steps = buildJourney(definitions.data?.data ?? [], events)
  const loose = looseEvents(definitions.data?.data ?? [], events)

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="font-semibold">{t('tracking.title')}</p>
          <p className="text-sm text-muted-foreground">
            {steps.length > 0 ? t('tracking.journeyHint') : t('tracking.description')}
          </p>
        </div>

        <PermissionGuard permission="tracking_events.create">
          <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
            <Plus className="size-4" aria-hidden />
            {t('tracking.add')}
          </Button>
        </PermissionGuard>
      </div>

      {error ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : isPending || definitions.isPending ? (
        <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
      ) : steps.length === 0 && events.length === 0 ? (
        <EmptyState title={t('tracking.empty')} description={t('tracking.noJourney')} />
      ) : (
        <>
          {steps.length > 0 ? <JourneyTimeline steps={steps} /> : null}

          {loose.length > 0 ? (
            <section className="flex flex-col gap-2 border-t pt-4">
              <p className="text-sm font-medium">
                {steps.length > 0 ? t('tracking.otherEvents') : t('tracking.rawJournal')}
              </p>
              <ol className="flex flex-col">
                {loose.map((event) => (
                  <TrackingEventCard
                    key={event.id}
                    event={event}
                    onOpen={() => setOpened(event)}
                  />
                ))}
              </ol>
            </section>
          ) : null}
        </>
      )}

      <TrackingEventDetailDrawer event={opened} onClose={() => setOpened(null)} />

      {creating ? (
        <NewTrackingEventDialog orderId={orderId} open onOpenChange={setCreating} />
      ) : null}
    </div>
  )
}
