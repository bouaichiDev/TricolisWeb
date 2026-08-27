import { UserRound } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import type { Tour } from '@/modules/tours/types/tour'
import { useAuth } from '@/shared/hooks/useAuth'

import type { MapTarget } from './MapFocus'
import { PlannedToursPanel } from './PlannedToursPanel'
import { PoolMapPanel } from './PoolMapPanel'
import { PlanningMap } from '../PlanningMap'
import type { PoolOrder } from '../../types/pool'

interface PlanningMapScreenProps {
  orders: PoolOrder[]
  tours: Tour[]
  search: string
  onSearchChange: (search: string) => void
  selectedTourId: string | null
  onSelectTour: (tourId: string) => void
  onPlanOrder: (orderId: string) => void
  isPending: boolean
}

/**
 * La planification sur carte, en trois panneaux.
 *
 * À gauche ce qui roule déjà, au centre le terrain, à droite ce qui attend. La
 * carte occupe l'essentiel de la hauteur : c'est elle qu'on interroge, et une
 * vignette de trente rem ne répond pas à « ces deux commandes sont-elles
 * voisines ? ».
 *
 * **Le planificateur est nommé en haut.** Une tournée brouillon n'appartient
 * qu'à qui l'a ouverte — le serveur refuse aux autres de la modifier — et voir
 * son propre nom rappelle pourquoi une tournée voisine peut rester inerte.
 *
 * Les trois panneaux partagent un seul état : la tournée choisie reçoit, qu'on
 * planifie depuis la liste ou depuis la bulle d'un marqueur.
 */
export function PlanningMapScreen({
  orders,
  tours,
  search,
  onSearchChange,
  selectedTourId,
  onSelectTour,
  onPlanOrder,
  isPending,
}: PlanningMapScreenProps) {
  const { t } = useTranslation()
  const { user } = useAuth()

  // Le point demande, et un jeton pour distinguer deux demandes identiques.
  const [focus, setFocus] = useState<MapTarget | null>(null)

  const aim = (latitude: number, longitude: number) =>
    setFocus({ latitude, longitude, token: Date.now() })

  const aimAtOrder = (order: PoolOrder) => {
    const placed = order.services.find(
      (service) => service.latitude !== null && service.longitude !== null,
    )

    if (placed !== undefined) {
      aim(placed.latitude as number, placed.longitude as number)
    }
  }

  const drafts = tours.filter((tour) => tour.status === 'draft')

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-card px-3 py-2">
        <p className="flex items-center gap-2 text-sm">
          <UserRound className="size-4 text-muted-foreground" aria-hidden />
          <span className="text-muted-foreground">{t('planning.plannedBy')}</span>
          <span className="font-medium">
            {user === null ? '—' : `${user.firstName} ${user.lastName}`}
          </span>
        </p>

        <p className="text-sm text-muted-foreground">
          {selectedTourId === null
            ? t('planning.pickTour')
            : t('planning.receiving', {
                number: tours.find((tour) => tour.id === selectedTourId)?.tourNumber ?? '',
              })}
        </p>
      </div>

      <div className="grid gap-3 xl:grid-cols-[16rem_minmax(0,1fr)_18rem]">
        <section className="flex max-h-[70vh] flex-col gap-2 overflow-y-auto rounded-lg border bg-card p-2">
          <h2 className="text-sm font-semibold">{t('planning.plannedTours')}</h2>

          <PlannedToursPanel
            tours={tours}
            selectedTourId={selectedTourId}
            onSelectTour={onSelectTour}
            onFocusStop={aim}
          />
        </section>

        {/* La hauteur vient d'ici : Leaflet mesure son conteneur au montage, et
            un parent de hauteur automatique le laisserait a zero pixel. */}
        <div className="h-[70vh]">
          <PlanningMap
            orders={orders}
            tours={tours}
            focus={focus}
            className="min-h-0 w-full flex-1 rounded-lg border"
            onPlanOrder={selectedTourId === null ? undefined : onPlanOrder}
          />
        </div>

        <section className="flex max-h-[70vh] flex-col gap-2 rounded-lg border bg-card p-2">
          <h2 className="text-sm font-semibold">
            {t('planning.pool')}
            <span className="ml-1 font-normal text-muted-foreground">({orders.length})</span>
          </h2>

          <PoolMapPanel
            orders={orders}
            search={search}
            onSearchChange={onSearchChange}
            onFocus={aimAtOrder}
            onPlan={selectedTourId === null ? undefined : onPlanOrder}
            isPending={isPending}
          />
        </section>
      </div>

      {drafts.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('planning.noDraftHint')}</p>
      ) : null}
    </div>
  )
}
