import { MapPin, Phone } from 'lucide-react'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDateTime } from '@/shared/utils/format'

import type { TourStop } from '../types'
import { StopActions } from './StopActions'

const place = (address: TourStop['address']) =>
  address === null ? '—' : [address.line1, [address.postalCode, address.city].filter(Boolean).join(' ')].filter(Boolean).join(' · ')

/**
 * Les arrêts dans l'ordre de la tournée : où aller, quand, et les services à y
 * réaliser — numéro de service (celui des étiquettes), colis, poids, contacts
 * sur place. Aucune donnée commerciale n'arrive jusqu'ici.
 */
export function TourStops({ tourId, stops }: { tourId: string; stops: TourStop[] }) {
  return (
    <SectionCard title="Arrêts" description={`${stops.length} arrêt${stops.length > 1 ? 's' : ''}, dans l’ordre de passage.`}>
      <ol className="space-y-4">
        {stops.map((stop) => (
          <li key={stop.id} className="rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div className="flex items-start gap-3">
                <span className="bg-primary text-primary-foreground flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold">
                  {stop.sequence}
                </span>
                <div>
                  <p className="font-medium">{stop.address?.name ?? 'Arrêt'}</p>
                  <p className="text-muted-foreground flex items-center gap-1 text-sm">
                    <MapPin className="size-3.5" aria-hidden />
                    {place(stop.address)}
                  </p>
                  {stop.address?.instructions && <p className="text-muted-foreground mt-1 text-sm">Consignes : {stop.address.instructions}</p>}
                </div>
              </div>
              <div className="flex flex-col items-end gap-1 text-sm">
                <StatusBadge code={stop.status} label={stop.statusLabel} />
                <span className="text-muted-foreground">Prévu {formatDateTime(stop.plannedArrivalAt)}</span>
              </div>
            </div>
            <ul className="mt-3 space-y-2 border-t pt-3">
              {stop.services.map((service) => (
                <li key={service.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                  <span>
                    <span className="font-medium">{service.serviceNumber}</span>
                    <span className="text-muted-foreground"> — {service.serviceName ?? 'Service'}</span>
                  </span>
                  <span className="text-muted-foreground">
                    {service.packageCount ?? 0} colis · {Number(service.weight ?? 0).toLocaleString('fr-CH')} kg
                  </span>
                  {service.contacts.map((contact, index) => (
                    <span key={index} className="text-muted-foreground flex w-full items-center gap-1">
                      <Phone className="size-3.5" aria-hidden />
                      {`${contact.firstName ?? ''} ${contact.lastName ?? ''}`.trim()} {contact.phone ?? contact.mobile ?? ''}
                    </span>
                  ))}
                </li>
              ))}
              {stop.services.length === 0 && <li className="text-muted-foreground text-sm">Aucun service à cet arrêt.</li>}
            </ul>
            <StopActions tourId={tourId} stop={stop} />
          </li>
        ))}
      </ol>
    </SectionCard>
  )
}
