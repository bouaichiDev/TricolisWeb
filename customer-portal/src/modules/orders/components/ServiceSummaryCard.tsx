import { MapPin, User } from 'lucide-react'
import type { ReactNode } from 'react'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Badge } from '@/shared/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/shared/components/ui/card'
import { formatDate } from '@/shared/utils/format'

import type { OrderServiceView } from '../types'

interface ServiceSummaryCardProps {
  service: OrderServiceView
  /** Numéro de chaque colis de la commande, par identifiant. */
  packageNumber: Map<string, number>
  footer?: ReactNode
}

/** Un service de la commande : créneau, adresse, contacts, consignes, colis. */
export function ServiceSummaryCard({ service, packageNumber, footer }: ServiceSummaryCardProps) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0">
        <CardTitle className="text-base">
          {service.serviceNumber} — {service.service?.name ?? 'Service'}
        </CardTitle>
        <StatusBadge code={service.status.code} label={service.status.label} color={service.status.color} />
      </CardHeader>
      <CardContent className="space-y-3 text-sm">
        <p>
          {formatDate(service.requestedDate)}
          {service.requestedFrom && ` · ${service.requestedFrom}–${service.requestedTo ?? ''}`}
        </p>
        {service.address && (
          <p className="flex gap-2">
            <MapPin className="text-muted-foreground mt-0.5 size-4 shrink-0" aria-hidden />
            <span>
              {service.address.name && <span className="block font-medium">{service.address.name}</span>}
              {service.address.addressLine1}, {service.address.postalCode} {service.address.city} ({service.address.country})
            </span>
          </p>
        )}
        {service.contacts.map((contact, index) => (
          <p key={index} className="flex gap-2">
            <User className="text-muted-foreground mt-0.5 size-4 shrink-0" aria-hidden />
            <span>
              {`${contact.firstName ?? ''} ${contact.lastName ?? ''}`.trim()}
              {contact.phone && ` · ${contact.phone}`}
              {contact.isPrimary && <Badge variant="secondary" className="ml-2">Principal</Badge>}
            </span>
          </p>
        ))}
        {service.instructions && <p className="text-muted-foreground">{service.instructions}</p>}
        {service.packages.length > 0 && (
          <p className="text-muted-foreground">
            Colis : {service.packages.map((link) => packageNumber.get(link.packageId) ?? '?').join(', ')}
          </p>
        )}
        {footer}
      </CardContent>
    </Card>
  )
}
