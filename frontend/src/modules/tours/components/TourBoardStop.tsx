import { ChevronDown, ChevronRight, Clock, MapPin, Timer, X } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'

import { TourStopOrders } from './TourStopOrders'
import type { TourStop } from '../types/tour'

interface TourBoardStopProps {
  stop: TourStop
  /** Absent quand la tournée ne laisse rien retirer. */
  onUnplan?: (orderServiceIds: string[]) => void
}

/**
 * Un arrêt dans une colonne : son rang, son adresse, ce qu'il porte.
 *
 * **Replié par défaut.** Une colonne de dix arrêts dépliés ne se lit plus ; ce
 * qu'on compare d'abord, c'est l'ordre et les lieux. Le détail — destinataire,
 * colis, poids, temps sur place — s'ouvre à la demande, arrêt par arrêt.
 */
export function TourBoardStop({ stop, onUnplan }: TourBoardStopProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  // Ce qui attend une confirmation : les services vises, ou null. Rendre au
  // pool defait un travail de planification, et un clic manque ne se rattrape
  // qu'en replanifiant.
  const [confirming, setConfirming] = useState<string[] | null>(null)

  const serviceIds = stop.orderServiceIds ?? []
  const orders = stop.orders ?? []

  return (
    <div className="rounded-md border px-2 py-1.5">
      <div className="flex items-start gap-2">
        <span className="flex size-6 shrink-0 items-center justify-center rounded border text-xs font-medium">
          {stop.sequence}
        </span>

        <button
          type="button"
          onClick={() => setOpen((current) => !current)}
          aria-expanded={open}
          aria-label={t(open ? 'tours.collapseStop' : 'tours.expandStop')}
          className="min-w-0 flex-1 text-left">
          <span className="flex items-center gap-1 truncate text-xs">
            {open ? (
              <ChevronDown className="size-3 shrink-0 text-muted-foreground" aria-hidden />
            ) : (
              <ChevronRight className="size-3 shrink-0 text-muted-foreground" aria-hidden />
            )}
            <MapPin className="size-3 shrink-0 text-muted-foreground" aria-hidden />
            {stop.addressLabel ?? stop.addressId}
          </span>

          <span className="flex flex-wrap items-center gap-2 pl-4 text-[11px] text-muted-foreground">
            <StatusBadge status={stop.status} source="tour_stop" />
            {stop.serviceCount === undefined ? null : (
              <span className="flex items-center gap-1">
                <Clock className="size-3" aria-hidden />
                {t('tours.serviceCount', { count: stop.serviceCount })}
              </span>
            )}
            {stop.totalServiceMinutes === undefined || stop.totalServiceMinutes === 0 ? null : (
              <span className="flex items-center gap-1">
                <Timer className="size-3" aria-hidden />
                {t('tours.minutes', { count: stop.totalServiceMinutes })}
              </span>
            )}
          </span>
        </button>

        {onUnplan !== undefined && serviceIds.length > 0 ? (
          <button
            type="button"
            title={t('planning.unplanStop')}
            aria-label={t('planning.unplanStop')}
            className="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:text-destructive"
            onClick={() => setConfirming(serviceIds)}
          >
            <X className="size-3.5" aria-hidden />
          </button>
        ) : null}
      </div>

      {open ? (
        <TourStopOrders orders={orders} onUnplan={(ids) => setConfirming(ids)} />
      ) : null}

      <ConfirmDialog
        open={confirming !== null}
        onOpenChange={(next) => (next ? undefined : setConfirming(null))}
        title={t('planning.unplanConfirmTitle')}
        description={t('planning.unplanConfirmBody', { count: confirming?.length ?? 0 })}
        confirmLabel={t('planning.unplanConfirm')}
        variant="destructive"
        onConfirm={() => {
          if (confirming !== null) onUnplan?.(confirming)
          setConfirming(null)
        }}
      />
    </div>
  )
}
