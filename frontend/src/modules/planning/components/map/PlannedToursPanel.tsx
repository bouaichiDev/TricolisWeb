import { Check, IdCard, Package, Truck } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import type { Tour } from '@/modules/tours/types/tour'
import { StatusBadge } from '@/shared/components/data/StatusBadge'

interface PlannedToursPanelProps {
  tours: Tour[]
  /** Celle qui recevra : seul un brouillon peut être choisi. */
  selectedTourId: string | null
  onSelectTour: (tourId: string) => void
  /** Montre un arrêt sur la carte. */
  onFocusStop: (latitude: number, longitude: number) => void
}

/**
 * Ce qui est déjà planifié, tournée par tournée.
 *
 * La carte montre où passent les camions ; ce panneau dit **lesquels**, avec
 * quoi, et dans quel ordre. Cliquer un arrêt l'amène au centre — le chercher à
 * l'œil parmi trente marqueurs ne marche qu'avec trois.
 *
 * Choisir une tournée ne se fait que sur un brouillon : les autres n'acceptent
 * plus de commande, et proposer le choix laisserait croire le contraire.
 */
export function PlannedToursPanel({
  tours,
  selectedTourId,
  onSelectTour,
  onFocusStop,
}: PlannedToursPanelProps) {
  const { t } = useTranslation()

  if (tours.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('planning.noPlannedTour')}</p>
  }

  return (
    <ul className="flex flex-col gap-3">
      {tours.map((tour) => {
        const draft = tour.status === 'draft'
        const selected = tour.id === selectedTourId

        return (
          <li
            key={tour.id}
            className={`rounded-lg border p-2 ${selected ? 'border-primary bg-primary/5' : ''}`}
          >
            <button
              type="button"
              disabled={!draft}
              onClick={() => onSelectTour(tour.id)}
              className="flex w-full items-center justify-between gap-2 text-left disabled:cursor-default"
            >
              <span className="flex min-w-0 items-center gap-1.5 font-medium">
                {selected ? <Check className="size-4 shrink-0 text-primary" aria-hidden /> : null}
                <span className="truncate">{tour.tourNumber}</span>
              </span>
              <StatusBadge status={tour.status} source="tour" />
            </button>

            <p className="mt-1 flex flex-wrap gap-x-3 text-[11px] text-muted-foreground">
              <span className="flex items-center gap-1" title={t('tours.fields.driver')}>
                <IdCard className="size-3" aria-hidden />
                {tour.driverName ?? t('tours.unassigned')}
              </span>
              <span className="flex items-center gap-1" title={t('tours.fields.vehicle')}>
                <Truck className="size-3" aria-hidden />
                {tour.vehicleRegistration ?? t('tours.unassigned')}
              </span>
              <span className="flex items-center gap-1" title={t('tours.fields.packages')}>
                <Package className="size-3" aria-hidden />
                {tour.totalPackages}
              </span>
            </p>

            <ol className="mt-1.5 flex flex-col gap-0.5">
              {(tour.stops ?? []).map((stop) => (
                <li key={stop.id}>
                  <button
                    type="button"
                    disabled={
                      stop.latitude === null ||
                      stop.latitude === undefined ||
                      stop.longitude === null ||
                      stop.longitude === undefined
                    }
                    onClick={() =>
                      onFocusStop(stop.latitude as number, stop.longitude as number)
                    }
                    className="flex w-full items-center gap-1.5 rounded px-1 py-0.5 text-left text-[11px] hover:bg-muted disabled:opacity-50"
                  >
                    <span className="flex size-4 shrink-0 items-center justify-center rounded-full border text-[10px] font-medium">
                      {stop.sequence}
                    </span>
                    <span className="truncate">{stop.addressLabel ?? stop.addressId}</span>
                  </button>
                </li>
              ))}
            </ol>
          </li>
        )
      })}
    </ul>
  )
}
