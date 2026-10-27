import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { DetailField } from '@/shared/components/layout/DetailField'
import { formatDateTime } from '@/shared/utils/format'
import { formatSlot, slotSourceLabel } from '@/shared/utils/slot'

import type { TrackedService } from '../types'
import { TrackingProgress } from './TrackingProgress'

/**
 * Le détail d'une prestation suivie : statut, adresse, créneau, passage,
 * progression dans le parcours, preuve de livraison.
 */
export function ServiceTrackingCard({ service }: { service: TrackedService }) {
  const address = service.address
  const stop = service.stop

  return (
    <div className="flex flex-col gap-3 rounded-lg border p-4" data-testid="service-tracking-card">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="font-medium">
          {service.service?.name ?? 'Service'} <span className="text-muted-foreground font-normal">· {service.serviceNumber}</span>
        </p>
        <StatusBadge code={service.status.code} label={service.status.label} color={service.status.color} />
      </div>

      {service.progress && <TrackingProgress percent={service.progress.percent} label={`Étapes franchies : ${service.progress.reached} / ${service.progress.total}`} />}

      <dl className="grid gap-x-6 sm:grid-cols-2">
        <DetailField label="Adresse">
          {address ? [address.name, address.addressLine1, [address.postalCode, address.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') : null}
        </DetailField>
        <DetailField label="Date / créneau">
          {service.slot ? (
            <>
              {formatSlot(service.slot)}
              <span className="text-muted-foreground block text-xs">{slotSourceLabel(service.slot.source)}</span>
            </>
          ) : null}
        </DetailField>
        {stop && (
          <DetailField label="Passage">
            <StatusBadge code={stop.status.code} label={stop.status.label} color={stop.status.color} />
            {stop.actualArrivalAt && <span className="block text-xs">Arrivée : {formatDateTime(stop.actualArrivalAt)}</span>}
            {stop.actualDepartureAt && <span className="block text-xs">Terminé : {formatDateTime(stop.actualDepartureAt)}</span>}
          </DetailField>
        )}
        {service.proofOfDelivery && (
          <DetailField label="Preuve de livraison">
            {formatDateTime(service.proofOfDelivery.deliveredAt)}
            {service.proofOfDelivery.recipientName && <span className="block text-xs">Réceptionné par {service.proofOfDelivery.recipientName}</span>}
          </DetailField>
        )}
      </dl>
    </div>
  )
}
