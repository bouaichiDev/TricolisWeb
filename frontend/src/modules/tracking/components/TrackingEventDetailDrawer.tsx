import { useTranslation } from 'react-i18next'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/shared/components/ui/sheet'
import { formatDateTime } from '@/shared/utils/format'

import { hasCoordinates, type TrackingEvent } from '../types/trackingEvent'

interface TrackingEventDetailDrawerProps {
  event: TrackingEvent | null
  onClose: () => void
}

/**
 * Détail d'un événement de suivi.
 *
 * Le §8 demande d'afficher le service, la tournée et l'arrêt « si disponible ».
 * Le backend n'en renvoie que les **identifiants** — `orderServiceId`, `tourId`,
 * `tourStopId` — sans les charger. Les afficher bruts n'apprendrait rien ; ils
 * sont donc montrés seulement quand ils existent, et présentés comme des
 * références techniques.
 *
 * Il n'y a **aucun bouton « Voir sur la carte »** : le §8 le conditionne à
 * l'existence d'un composant carte, et le projet n'en a pas.
 */
export function TrackingEventDetailDrawer({ event, onClose }: TrackingEventDetailDrawerProps) {
  const { t } = useTranslation()

  const references = [
    { key: 'orderService', value: event?.orderServiceId },
    { key: 'tour', value: event?.tourId },
    { key: 'tourStop', value: event?.tourStopId },
  ].filter((item) => item.value != null && item.value !== '')

  const creator = event?.creator

  return (
    <Sheet open={event !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>{event?.eventType ?? ''}</SheetTitle>
          <SheetDescription>
            {event ? formatDateTime(event.occurredAt) : ''}
          </SheetDescription>
        </SheetHeader>

        {event ? (
          <div className="flex flex-col gap-4 px-4 pb-6">
            <div className="flex items-center gap-2">
              <span className="text-sm text-muted-foreground">{t('tracking.fields.status')}</span>
              <StatusBadge status={event.status} />
            </div>

            {event.description ? (
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('tracking.fields.description')}
                </p>
                <p className="mt-1 whitespace-pre-wrap text-sm">{event.description}</p>
              </div>
            ) : null}

            {hasCoordinates(event) ? (
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('tracking.fields.coordinates')}
                </p>
                <p className="mt-1 font-mono text-sm">
                  {event.latitude}, {event.longitude}
                </p>
              </div>
            ) : null}

            {creator ? (
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('tracking.fields.createdBy')}
                </p>
                <p className="mt-1 text-sm">
                  {`${creator.firstName} ${creator.lastName}`.trim()}
                </p>
              </div>
            ) : null}

            {references.length > 0 ? (
              <div className="border-t pt-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('tracking.references')}
                </p>
                <dl className="mt-1 flex flex-col gap-1">
                  {references.map((item) => (
                    <div key={item.key} className="flex gap-2 text-sm">
                      <dt className="text-muted-foreground">{t(`tracking.fields.${item.key}`)}</dt>
                      <dd className="truncate font-mono text-xs">{item.value}</dd>
                    </div>
                  ))}
                </dl>
              </div>
            ) : null}
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
