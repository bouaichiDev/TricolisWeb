import { useTranslation } from 'react-i18next'

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

import { TourForm } from './TourForm'
import { useUpdateTour } from '../hooks/useTours'
import type { Tour } from '../types/tour'

interface TourEditDialogProps {
  tour: Tour | null
  onClose: () => void
}

/**
 * Modifier une tournée sans quitter le plan.
 *
 * Affecter un chauffeur ou décaler l'heure de départ se décide **en comparant**
 * les colonnes voisines. Passer par `/tours/:id/edit` fait perdre cette vue, et
 * l'on revient sans savoir si le choix tenait encore.
 *
 * Le même formulaire qu'à la page d'édition : deux saisies pour une même chose
 * finiraient par diverger, et l'une des deux oublierait une règle.
 */
export function TourEditDialog({ tour, onClose }: TourEditDialogProps) {
  return (
    <Dialog open={tour !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
        {/* Le corps est un composant à part, et non une fonction interne : défini
            ici, il serait recréé à chaque rendu et le formulaire se remonterait,
            perdant la saisie en cours. */}
        {tour === null ? null : <TourEditBody tour={tour} onDone={onClose} />}
      </DialogContent>
    </Dialog>
  )
}

function TourEditBody({ tour, onDone }: { tour: Tour; onDone: () => void }) {
  const { t } = useTranslation()
  const update = useUpdateTour(tour.id)

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('tours.editTitle')}</DialogTitle>
        <DialogDescription>{tour.tourNumber}</DialogDescription>
      </DialogHeader>

      <TourForm
        tour={tour}
        isPending={update.isPending}
        onCancel={onDone}
        onSubmit={async (payload) => {
          await update.mutateAsync(payload)
          onDone()
        }}
      />
    </>
  )
}
