import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'

import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { Button } from '@/shared/components/ui/button'

import { JourneyTimeline } from './JourneyTimeline'
import { TrackingEventCard } from './TrackingEventCard'
import { TrackingEventDetailDrawer } from './TrackingEventDetailDrawer'
import { VehiclePositionPanel } from './VehiclePositionPanel'
import { useOrderTracking, useTrackingDefinitions } from '../hooks/useTracking'
import { buildJourney, looseEvents, type CarriedService } from '../schemas/journey'
import type { TrackingEvent } from '../types/trackingEvent'

interface OrderTrackingTabProps {
  orderId: string
  /**
   * Les prestations que la commande porte.
   *
   * Elles servent deux fois : elles écartent du parcours les étapes décrites
   * pour une prestation absente — une commande sans montage ne doit pas
   * afficher « monté » comme une étape à venir qui n'arrivera jamais — et elles
   * disent de quelle prestation vient chaque événement, pour qu'un « livré »
   * publié par le chargement ne franchisse pas l'étape de la livraison.
   */
  services: CarriedService[]
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
 * Rien ne se saisit ici, et rien ne s'y modifie : les étapes sont décrites une
 * fois pour toute l'organisation dans la configuration du parcours, et publiées
 * par les changements de statut. Un bouton par commande ferait de chaque
 * commande une exception à un parcours censé être commun.
 */
export function OrderTrackingTab({ orderId, services, active }: OrderTrackingTabProps) {
  const { t } = useTranslation()
  const [opened, setOpened] = useState<TrackingEvent | null>(null)

  const definitions = useTrackingDefinitions(active)
  const { data, isPending, error, refetch } = useOrderTracking(
    orderId,
    { page: 1, perPage: 100, sort: 'occurred_at', direction: 'asc' },
    active,
  )

  const events = data?.data ?? []
  const steps = buildJourney(definitions.data?.data ?? [], events, services)
  const loose = looseEvents(definitions.data?.data ?? [], events, services)

  // Une etape franchie mais pas depassee : c'est la que le camion roule.
  const currentIndex = steps.findIndex((step) => step.occurredAt === null)
  const hasLiveStep = steps.some(
    (step, index) =>
      step.definition.isLive && (index === currentIndex || index === currentIndex - 1),
  )

  return (
    <div className="flex flex-col gap-4">
      <div>
        <p className="font-semibold">{t('tracking.title')}</p>
        <p className="text-sm text-muted-foreground">
          {steps.length > 0 ? t('tracking.journeyHint') : t('tracking.description')}
        </p>
      </div>

      {error ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : isPending || definitions.isPending ? (
        <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
      ) : steps.length === 0 && events.length === 0 ? (
        <EmptyState
          title={t('tracking.empty')}
          description={t('tracking.noJourney')}
          action={
            /* Le parcours vaut pour toutes les commandes : il se decrit une fois
               dans la configuration, pas commande par commande. */
            <PermissionGuard permission="tracking_event_definitions.view">
              <Button type="button" variant="outline" size="sm" asChild>
                <Link to="/journey">{t('tracking.configureJourney')}</Link>
              </Button>
            </PermissionGuard>
          }
        />
      ) : (
        <>
          {/* Le suivi n'a de sens que si une etape en cours l'annonce. */}
          <VehiclePositionPanel orderId={orderId} enabled={hasLiveStep} />

          {steps.length > 0 ? <JourneyTimeline steps={steps} /> : null}

          {/* Le journal brut ne sort que faute de parcours. Des qu'un parcours
              existe, il dit tout ce qu'il y a a dire : lui adjoindre une liste
              d'evenements qu'aucune etape ne revendique — un « livre » publie
              par le chargement, un code supprime depuis — redonnait le journal
              que le parcours remplace, et rendait l'ecran illisible. */}
          {steps.length === 0 && loose.length > 0 ? (
            <section className="flex flex-col gap-2 border-t pt-4">
              <p className="text-sm font-medium">{t('tracking.rawJournal')}</p>
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
    </div>
  )
}
