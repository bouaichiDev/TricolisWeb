import { useTranslation } from 'react-i18next'

import { PlanningMap } from '@/modules/planning/components/PlanningMap'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { formatDate } from '@/shared/utils/format'

import type { Tour } from '../types/tour'

interface TourMapDialogProps {
  tour: Tour | null
  onClose: () => void
}

/**
 * La carte d'une seule tournée, ouverte depuis sa colonne.
 *
 * L'écran de planification montre toutes les tournées à la fois — c'est ce
 * qu'on veut pour comparer. Ici la question est autre : **où passe celle-ci**,
 * et dans quel ordre. Une seule tournée tracée répond mieux que dix.
 *
 * Le pool n'y figure pas : on regarde ce qui est déjà planifié, pas ce qui
 * attend. Les bulles des arrêts mènent aux commandes.
 */
export function TourMapDialog({ tour, onClose }: TourMapDialogProps) {
  const { t } = useTranslation()

  return (
    <Dialog open={tour !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent className="max-w-4xl">
        {tour === null ? null : (
          <>
            <DialogHeader>
              <DialogTitle>{t('tours.mapTitle', { number: tour.tourNumber })}</DialogTitle>
              <DialogDescription>
                {tour.tourDate === null ? t('tours.mapHint') : formatDate(tour.tourDate)}
              </DialogDescription>
            </DialogHeader>

            <PlanningMap orders={[]} tours={[tour]} />
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}
