import { Crosshair, IdCard, Package, Truck } from 'lucide-react'
import { Fragment, useMemo } from 'react'
import { useTranslation } from 'react-i18next'

import { TourBoardLeg } from '@/modules/tours/components/TourBoardLeg'
import { TourBoardStop } from '@/modules/tours/components/TourBoardStop'
import type { Tour } from '@/modules/tours/types/tour'
import { legsByStop } from '@/modules/tours/utils/tourLegs'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Button } from '@/shared/components/ui/button'

interface PlannedToursPanelProps {
  tours: Tour[]
  /** Celle qu'on regarde et qui reçoit ; seule elle est détaillée. */
  selectedTourId: string | null
  onSelectTour: (tourId: string) => void
  /** Montre un arrêt sur la carte. */
  onFocusStop: (latitude: number, longitude: number) => void
  onUnplan?: (tourId: string, orderServiceIds: string[]) => void
  /** Tournée engagée : tant qu'elle n'est pas conclue, on n'en change pas. */
  lockedTourId?: string | null
  /** Ce brouillon est-il tenu par un autre planificateur ? */
  isHeldByOther?: (tour: Tour) => boolean
}

/**
 * La tournée qu'on regarde, dépliée comme dans les colonnes.
 *
 * **Une seule à la fois.** Empiler les tournées du jour redonnait la vue en
 * colonnes en plus étroit, sans rien apporter : ce qu'on vient chercher ici,
 * c'est le détail de celle qu'on est en train de composer, en face du terrain.
 * Les autres restent accessibles par leur numéro, au-dessus.
 *
 * Les arrêts sont ceux du tableau — même composant, donc même dépliage, mêmes
 * commandes et même retrait. Deux affichages d'un arrêt finiraient par diverger.
 */
export function PlannedToursPanel({
  tours,
  selectedTourId,
  onSelectTour,
  onFocusStop,
  onUnplan,
  lockedTourId,
  isHeldByOther,
}: PlannedToursPanelProps) {
  const { t } = useTranslation()

  const selected = tours.find((tour) => tour.id === selectedTourId) ?? null

  // Rangés une fois pour la tournée ouverte : le cumul se lit sur l'ordre du
  // serveur, pas sur celui de l'affichage.
  const legs = useMemo(() => legsByStop(selected?.legs ?? []), [selected?.legs])
  const others = tours.filter((tour) => tour.id !== selectedTourId)

  if (tours.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('planning.noPlannedTour')}</p>
  }

  return (
    <div className="flex flex-col gap-3">
      {others.length === 0 ? null : (
        <div className="flex flex-col gap-1">
          <p className="text-[11px] uppercase tracking-wide text-muted-foreground">
            {t('planning.otherTours')}
          </p>
          <div className="flex flex-wrap gap-1">
            {others.map((tour) => {
              const held = isHeldByOther?.(tour) ?? false
              // « Ailleurs » veut dire une autre : la tournee qu'on retient
              // reste ouverte, sans quoi on ne pourrait plus la conclure.
              const engagedElsewhere =
                lockedTourId !== null && lockedTourId !== undefined && lockedTourId !== tour.id

              return (
                <Button
                  key={tour.id}
                  type="button"
                  size="sm"
                  variant="outline"
                  // Ouvrir une tournee tenue par un autre reste permis : le
                  // §25 veut qu'on la voie en lecture seule, avec le nom de qui
                  // la tient. Seul l'engagement ailleurs ferme le passage.
                  disabled={engagedElsewhere}
                  title={
                    engagedElsewhere
                      ? t('planning.concludeFirst')
                      : held
                        ? t('planning.heldByOther', { name: tour.plannedBy?.name ?? '' })
                        : tour.status === 'draft'
                          ? undefined
                          : t('planning.onlyDraftReceives')
                  }
                  onClick={() => onSelectTour(tour.id)}
                >
                  {tour.tourNumber}
                </Button>
              )
            })}
          </div>
        </div>
      )}

      {selected === null ? (
        <p className="text-sm text-muted-foreground">{t('planning.pickTour')}</p>
      ) : (
        <section className="flex flex-col gap-2">
          <div className="flex items-center justify-between gap-2">
            <span className="truncate font-medium">{selected.tourNumber}</span>
            <StatusBadge status={selected.status} source="tour" />
          </div>

          {/* Le §25 l'exige mot pour mot : qui tient le brouillon, et que l'on
              n'est ici qu'en lecture. */}
          {onUnplan === undefined && selected.status === 'draft' ? (
            <p className="rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-[11px] text-amber-900">
              {selected.plannedBy === null || selected.plannedBy === undefined
                ? t('planning.readOnlyHere')
                : t('planning.heldByOther', { name: selected.plannedBy.name })}
            </p>
          ) : null}

          <p className="flex flex-wrap gap-x-3 text-[11px] text-muted-foreground">
            <span className="flex items-center gap-1" title={t('tours.fields.driver')}>
              <IdCard className="size-3" aria-hidden />
              {selected.driverName ?? t('tours.unassigned')}
            </span>
            <span className="flex items-center gap-1" title={t('tours.fields.vehicle')}>
              <Truck className="size-3" aria-hidden />
              {selected.vehicleRegistration ?? t('tours.unassigned')}
            </span>
            <span className="flex items-center gap-1" title={t('tours.fields.packages')}>
              <Package className="size-3" aria-hidden />
              {selected.totalPackages}
            </span>
          </p>

          <div className="flex flex-col gap-1">
            {(selected.stops ?? []).length === 0 ? (
              <p className="text-xs text-muted-foreground">{t('tours.noStop')}</p>
            ) : (
              (selected.stops ?? []).map((stop) => {
                // Le trajet qui mene a cet arret, cumul compris. Le mode carte
                // le montre comme la vue en colonnes : c'est la meme decision
                // qu'on y prend, sur les memes chiffres.
                const arrival = legs.get(stop.id)

                const placed =
                  stop.latitude !== null &&
                  stop.latitude !== undefined &&
                  stop.longitude !== null &&
                  stop.longitude !== undefined

                return (
                  <Fragment key={stop.id}>
                    {arrival === undefined ? null : (
                      <TourBoardLeg leg={arrival.leg} cumulative={arrival.cumulative} />
                    )}

                    <div className="flex items-start gap-1">
                      <div className="min-w-0 flex-1">
                        <TourBoardStop
                          stop={stop}
                          onUnplan={
                            onUnplan === undefined ? undefined : (ids) => onUnplan(selected.id, ids)
                          }
                        />
                      </div>

                      {/* Un bouton, pas un double-clic : le second ne se devine
                          pas, et le clic simple deplie deja l'arret. */}
                      <button
                        type="button"
                        disabled={!placed}
                        title={placed ? t('planning.showOnMap') : t('planning.notPlaceable')}
                        aria-label={t('planning.showOnMap')}
                        className="mt-1.5 shrink-0 rounded p-1 text-muted-foreground transition-colors hover:text-primary disabled:opacity-40"
                        onClick={() => onFocusStop(stop.latitude as number, stop.longitude as number)}
                      >
                        <Crosshair className="size-3.5" aria-hidden />
                      </button>
                    </div>
                  </Fragment>
                )
              })
            )}
          </div>
        </section>
      )}
    </div>
  )
}
