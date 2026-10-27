import { Eye, MapPin, Pencil } from 'lucide-react'
import { Link } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { DetailField } from '@/shared/components/layout/DetailField'
import { Button } from '@/shared/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/shared/components/ui/sheet'
import { formatSlot, slotSourceLabel } from '@/shared/utils/slot'

import type { CalendarOrderEvent } from '../types'

interface OrderEventSheetProps {
  event: CalendarOrderEvent | null
  onClose: () => void
}

/**
 * Vue rapide d'une commande cliquée dans le calendrier (§8).
 *
 * Les boutons suivent `actions`, calculées par le backend : Voir, Modifier
 * (`canEdit`), Suivre (`canTrack`). Aucune règle de statut n'est réécrite ici.
 */
export function OrderEventSheet({ event, onClose }: OrderEventSheetProps) {
  const props = event?.extendedProps

  return (
    <Sheet open={event !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="w-full sm:max-w-md">
        {event && props ? (
          <>
            <SheetHeader>
              <SheetTitle>{props.reference}</SheetTitle>
              <SheetDescription>
                {props.customerReference ? `Votre référence : ${props.customerReference}` : 'Commande'}
              </SheetDescription>
            </SheetHeader>

            <div className="flex flex-col gap-4 overflow-y-auto px-4">
              <dl>
                <DetailField label="Statut">
                  <StatusBadge code={props.status.code} label={props.status.label} color={props.status.color} />
                </DetailField>
                <DetailField label="Date / créneau">
                  {formatSlot({ ...event, source: props.slotSource })}
                  <span className="text-muted-foreground block text-xs">{slotSourceLabel(props.slotSource)}</span>
                </DetailField>
              </dl>
              <div className="flex flex-col gap-2">
                <p className="text-muted-foreground text-sm">Services ({props.serviceCount})</p>
                <ul className="flex flex-col gap-2">
                  {props.services.map((service) => (
                    <li key={service.id} className="rounded-md border p-2 text-sm">
                      <div className="flex items-center justify-between gap-2">
                        <span className="font-medium">{service.name ?? 'Service'}</span>
                        <StatusBadge code={service.status.code} label={service.status.label} color={service.status.color} />
                      </div>
                      <span className="text-muted-foreground">{formatSlot(service)}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>

            {/* Un lien quitte le calendrier : la vue rapide se ferme aussitôt,
                sans attendre le chargement de la page visée. */}
            <SheetFooter>
              {props.actions.canView && (
                <Button asChild>
                  <Link to={paths.order(event.id)} onClick={onClose}>
                    <Eye className="size-4" aria-hidden /> Voir la commande
                  </Link>
                </Button>
              )}
              {props.actions.canEdit && (
                <Button asChild variant="outline">
                  <Link to={paths.orderEdit(event.id)} onClick={onClose}>
                    <Pencil className="size-4" aria-hidden /> Modifier
                  </Link>
                </Button>
              )}
              {props.actions.canTrack && (
                <Button asChild variant="outline">
                  <Link to={paths.orderTracking(event.id)} onClick={onClose}>
                    <MapPin className="size-4" aria-hidden /> Suivre
                  </Link>
                </Button>
              )}
            </SheetFooter>
          </>
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
