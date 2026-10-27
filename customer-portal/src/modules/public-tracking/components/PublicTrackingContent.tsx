import { EtaCard } from '@/modules/tracking/components/EtaCard'
import { TrackingMapSection } from '@/modules/tracking/components/TrackingMapSection'
import { TrackingProgress } from '@/modules/tracking/components/TrackingProgress'
import { TrackingTimeline } from '@/modules/tracking/components/TrackingTimeline'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatSlot } from '@/shared/utils/slot'

import type { PublicTracking } from '../types'

/**
 * Le suivi montré au client final (§29), bloc par bloc : un bloc fermé par
 * le transporteur (`null`) ne s'affiche pas, sans laisser de trou.
 */
export function PublicTrackingContent({ tracking }: { tracking: PublicTracking }) {
  return (
    <div className="flex flex-col gap-4">
      {(tracking.reference || tracking.status) && (
        <div className="flex flex-col gap-2">
          {tracking.reference && (
            <h1 className="text-2xl font-semibold tracking-tight">
              Commande {tracking.reference.customerReference ?? tracking.reference.reference}
            </h1>
          )}
          {tracking.status && <StatusBadge code={tracking.status.code} label={tracking.status.label} color={tracking.status.color} />}
        </div>
      )}

      {tracking.progress && <TrackingProgress percent={tracking.progress.percent} label="Progression" />}

      {tracking.eta && <EtaCard eta={tracking.eta} />}

      {tracking.services && tracking.services.length > 0 && (
        <SectionCard title="Date / créneau">
          <ul className="flex flex-col gap-2">
            {tracking.services.map((service, index) => (
              <li key={`${service.name}-${index}`} className="flex flex-col text-sm">
                <span className="font-medium">
                  {service.name}
                  {service.city && <span className="text-muted-foreground font-normal"> · {[service.postalCode, service.city].filter(Boolean).join(' ')}</span>}
                </span>
                <span>{formatSlot(service.slot)}</span>
                {service.status && <span className="text-muted-foreground text-xs">{service.status}</span>}
              </li>
            ))}
          </ul>
        </SectionCard>
      )}

      {tracking.map && (
        <SectionCard title="Carte">
          <TrackingMapSection points={tracking.map.points} position={tracking.map.currentPosition} />
        </SectionCard>
      )}

      {tracking.timeline && (
        <SectionCard title="Suivi">
          <TrackingTimeline items={tracking.timeline} />
        </SectionCard>
      )}

      {!tracking.isActive && <p className="text-muted-foreground text-center text-sm">Cette commande est terminée.</p>}
    </div>
  )
}
