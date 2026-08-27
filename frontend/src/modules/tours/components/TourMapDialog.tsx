import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { PlanningMapScreen } from '@/modules/planning/components/map/PlanningMapScreen'
import { usePlanIntoTour, usePlanningPool } from '@/modules/planning/hooks/usePlanning'
import type { PlanningRejection } from '@/modules/planning/types/pool'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

import type { Tour } from '../types/tour'

interface TourMapDialogProps {
  /** La tournée depuis laquelle on a ouvert la carte ; nulle quand elle est fermée. */
  tour: Tour | null
  /** Les autres tournées du jour : elles occupent le même terrain. */
  tours: Tour[]
  /** Date filtrée, pour n'offrir que les commandes du jour. */
  date?: string
  onClose: () => void
}

/**
 * La planification sur carte, en plein écran.
 *
 * Ouvrir « la carte d'une tournée » ne veut pas dire la regarder seule : ce
 * qu'on vient y faire, c'est **arbitrer** — cette commande ira-t-elle dans
 * celle-ci ou dans la voisine ? Une vignette montrant une seule tournée pose la
 * question sans donner de quoi y répondre.
 *
 * La fenêtre porte donc l'écran de planification entier : ce qui roule à
 * gauche, le terrain au centre, ce qui attend à droite. La tournée cliquée est
 * simplement celle qui reçoit au départ, si elle peut encore recevoir.
 *
 * Le §73 est tenu : ce sont les mêmes brouillons qu'en colonnes, pas une copie.
 */
export function TourMapDialog({ tour, tours, date, onClose }: TourMapDialogProps) {
  return (
    <Dialog open={tour !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent className="h-[92vh] w-[96vw] max-w-none gap-3 overflow-hidden p-4 sm:max-w-none">
        {tour === null ? null : <MapBody tour={tour} tours={tours} date={date} />}
      </DialogContent>
    </Dialog>
  )
}

/**
 * Corps de la fenêtre, monté seulement à l'ouverture.
 *
 * Composant à part et non fonction interne : défini dans le parent, il serait
 * recréé à chaque rendu et la carte se remonterait, perdant sa position.
 */
function MapBody({ tour, tours, date }: { tour: Tour; tours: Tour[]; date?: string }) {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')

  // La tournee cliquee recoit d'emblee, si elle le peut encore : une tournee
  // confirmee n'accepte plus rien, et la designer laisserait croire l'inverse.
  const [selectedTourId, setSelectedTourId] = useState<string | null>(
    tour.status === 'draft' ? tour.id : null,
  )

  const pool = usePlanningPool({
    page: 1,
    perPage: 50,
    requestedDate: date,
    search: search === '' ? undefined : search,
  })

  const plan = usePlanIntoTour()

  const report = (planned: string[], rejected: PlanningRejection[]) => {
    if (planned.length > 0) {
      toast.success(t('planning.planned', { count: planned.length }))
    }

    for (const refusal of rejected) {
      toast.warning(t(`planning.rejected.${refusal.reason}`, { defaultValue: refusal.reason }))
    }
  }

  return (
    <div className="flex h-full min-h-0 flex-col gap-3">
      <DialogHeader className="shrink-0">
        <DialogTitle>{t('tours.mapTitle', { number: tour.tourNumber })}</DialogTitle>
        <DialogDescription>{t('tours.mapHint')}</DialogDescription>
      </DialogHeader>

      <div className="min-h-0 flex-1 overflow-y-auto">
        <PlanningMapScreen
          orders={pool.data?.data ?? []}
          tours={tours}
          search={search}
          onSearchChange={setSearch}
          selectedTourId={selectedTourId}
          onSelectTour={setSelectedTourId}
          isPending={plan.isPending}
          onPlanOrder={(orderId) => {
            if (selectedTourId === null) return

            plan.mutate(
              { tourId: selectedTourId, orderIds: [orderId] },
              { onSuccess: (result) => report(result.planned, result.rejected) },
            )
          }}
        />
      </div>
    </div>
  )
}
