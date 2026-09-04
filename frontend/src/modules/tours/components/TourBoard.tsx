import { useTranslation } from 'react-i18next'

import type { PlanningDragPayload } from '@/modules/planning/dnd'

import { TourBoardColumn } from './TourBoardColumn'
import type { Tour } from '../types/tour'

interface TourBoardProps {
  tours: Tour[]
  emptyMessage: string
  /** Branché par l'écran qui montre un pool à côté ; absent ailleurs. */
  onPlanDrop?: (tourId: string, drag: PlanningDragPayload) => void
  onUnplan?: (tourId: string, orderServiceIds: string[]) => void
  onShowMap?: (tour: Tour) => void
  onEdit?: (tour: Tour) => void
}

/**
 * Les tournées côte à côte, une colonne chacune.
 *
 * C'est la lecture d'un planificateur : il compare des tournées entre elles —
 * celle-ci est pleine, celle-là part trop tard — ce qu'un tableau ligne à ligne
 * ne montre pas.
 *
 * Le défilement est **horizontal et propre à ce panneau** : la page ne doit pas
 * se décaler entière parce qu'on regarde la sixième tournée.
 */
export function TourBoard({
  tours,
  emptyMessage,
  onPlanDrop,
  onUnplan,
  onShowMap,
  onEdit,
}: TourBoardProps) {
  const { t } = useTranslation()

  if (tours.length === 0) {
    return <p className="text-sm text-muted-foreground">{emptyMessage}</p>
  }

  return (
    <div className="overflow-x-auto pb-2">
      <ul className="flex min-w-fit gap-3" aria-label={t('tours.viewBoard')}>
        {tours.map((tour) => (
          <TourBoardColumn
            key={tour.id}
            tour={tour}
            onPlanDrop={onPlanDrop}
            onUnplan={onUnplan}
            onShowMap={onShowMap}
            onEdit={onEdit}
          />
        ))}
      </ul>
    </div>
  )
}
