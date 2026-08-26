import { Map, Package, Route, Users, Weight } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import {
  carriesPlanningDrag,
  readPlanningDrag,
  type PlanningDragPayload,
} from '@/modules/planning/dnd'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDate } from '@/shared/utils/format'

import { TourBoardStop } from './TourBoardStop'
import type { Tour } from '../types/tour'

interface TourBoardColumnProps {
  tour: Tour
  /** Absent quand la vue est en lecture seule : la colonne n'accepte alors rien. */
  onPlanDrop?: (tourId: string, drag: PlanningDragPayload) => void
  /** Rend les services d'un arrêt au pool. */
  onUnplan?: (tourId: string, orderServiceIds: string[]) => void
  /** Ouvre la carte de cette seule tournée. */
  onShowMap?: (tour: Tour) => void
}

/**
 * Une tournée en colonne : son en-tête, ses chiffres, ses arrêts dans l'ordre.
 *
 * **Seul un brouillon reçoit un dépôt.** Une tournée confirmée ou partie n'a
 * plus à changer de contenu, et le serveur le refuserait : la colonne ne doit
 * pas laisser croire le contraire en s'illuminant au survol.
 */
export function TourBoardColumn({
  tour,
  onPlanDrop,
  onUnplan,
  onShowMap,
}: TourBoardColumnProps) {
  const { t } = useTranslation()
  const [over, setOver] = useState(false)

  const accepts = onPlanDrop !== undefined && tour.status === 'draft'

  // Ce qui a ete livre ne retourne pas dans le pool : seule une tournee non
  // terminee laisse retirer ce qu'elle porte. Le serveur applique la meme
  // regle — le bouton ne fait que ne pas promettre l'impossible.
  const releasable = onUnplan !== undefined && tour.status !== 'completed'

  const handlers = accepts
    ? {
        onDragOver: (event: React.DragEvent) => {
          if (!carriesPlanningDrag(event)) return

          // Sans ce refus du comportement par défaut, le navigateur n'autorise
          // aucun dépôt et le curseur affiche un panneau d'interdiction.
          event.preventDefault()
          event.dataTransfer.dropEffect = 'copy'
          setOver(true)
        },
        onDragLeave: () => setOver(false),
        onDrop: (event: React.DragEvent) => {
          event.preventDefault()
          setOver(false)

          const drag = readPlanningDrag(event)

          if (drag !== null) onPlanDrop(tour.id, drag)
        },
      }
    : {}

  return (
    <li
      {...handlers}
      data-testid={`tour-column-${tour.id}`}
      className={`flex w-72 shrink-0 flex-col gap-3 rounded-lg border bg-card p-3 transition-colors ${
        over ? 'border-primary bg-primary/5' : ''
      }`}
    >
      <div className="flex items-start justify-between gap-2">
        <Link to={`/tours/${tour.id}`} className="min-w-0 font-medium text-primary hover:underline">
          {tour.tourNumber}
        </Link>
        <span className="flex shrink-0 items-center gap-1">
          <StatusBadge status={tour.status} source="tour" />

          {/* Sans arret trace, la carte n'aurait rien a montrer. */}
          {onShowMap !== undefined && (tour.stops ?? []).length > 0 ? (
            <button
              type="button"
              title={t('tours.showMap')}
              aria-label={t('tours.showMap')}
              className="rounded p-1 text-muted-foreground transition-colors hover:text-primary"
              onClick={() => onShowMap(tour)}
            >
              <Map className="size-4" aria-hidden />
            </button>
          ) : null}
        </span>
      </div>

      {tour.tourDate === null ? null : (
        <p className="text-xs text-muted-foreground">{formatDate(tour.tourDate)}</p>
      )}

      <dl className="grid grid-cols-2 gap-2 text-xs">
        <Metric icon={Package} label={t('tours.fields.packages')} value={tour.totalPackages} />
        <Metric icon={Users} label={t('tours.fields.customers')} value={tour.totalCustomers} />
        <Metric icon={Weight} label={t('tours.fields.weightShort')} value={`${tour.totalWeight} kg`} />
        <Metric
          icon={Route}
          label={t('tours.fields.distance')}
          value={
            tour.distanceMeters === 0
              ? t('tours.notComputed')
              : `${(tour.distanceMeters / 1000).toFixed(1)} km`
          }
        />
      </dl>

      <div className="flex flex-col gap-1 border-t pt-2">
        {(tour.stops ?? []).length === 0 ? (
          <p className="text-xs text-muted-foreground">
            {accepts ? t('planning.dropHere') : t('tours.noStop')}
          </p>
        ) : (
          (tour.stops ?? []).map((stop) => (
            <TourBoardStop
              key={stop.id}
              stop={stop}
              onUnplan={releasable ? (ids) => onUnplan(tour.id, ids) : undefined}
            />
          ))
        )}
      </div>
    </li>
  )
}

function Metric({
  icon: Icon,
  label,
  value,
}: {
  icon: typeof Package
  label: string
  value: string | number
}) {
  return (
    <div className="min-w-0">
      <dt className="flex items-center gap-1 text-[11px] text-muted-foreground">
        <Icon className="size-3" aria-hidden />
        {label}
      </dt>
      <dd className="truncate font-medium">{value}</dd>
    </div>
  )
}
