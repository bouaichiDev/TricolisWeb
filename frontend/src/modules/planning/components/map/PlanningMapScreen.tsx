import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import type { Tour } from '@/modules/tours/types/tour'
import { useTourRoute } from '@/modules/tours/hooks/useTours'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { useAuth } from '@/shared/hooks/useAuth'

import type { MapTarget } from './MapFocus'
import { PlanningSessionBar } from './PlanningSessionBar'
import { useConcludePlan } from './useConcludePlan'
import { usePlanningSession } from './usePlanningSession'
import { deliveryPoint } from '../../focus'
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
  /** Absent quand la vue est en lecture seule. */
  onUnplan?: (tourId: string, orderServiceIds: string[]) => void
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
  onUnplan,
  isPending,
}: PlanningMapScreenProps) {
  const { t } = useTranslation()
  const { user } = useAuth()

  // Le point demande, et un jeton pour distinguer deux demandes identiques.
  const [focus, setFocus] = useState<MapTarget | null>(null)

  const aim = (latitude: number, longitude: number) =>
    setFocus({ latitude, longitude, token: Date.now() })

  const aimAtOrder = (order: PoolOrder) => {
    const point = deliveryPoint(order)

    if (point !== null) aim(point.latitude, point.longitude)
  }

  // Le trace de la seule tournee regardee : dessiner celui de toutes ferait
  // autant d'appels distants, pour un enchevetrement illisible.
  const route = useTourRoute(selectedTourId)

  const session = usePlanningSession(user?.id ?? null)
  const selected = tours.find((tour) => tour.id === selectedTourId) ?? null

  // Tout se deduit des donnees : un brouillon qui porte des arrets est un plan
  // commence, et le reste apres fermeture de la fenetre.
  const awaits = selected !== null && session.awaitsConclusion(selected)

  const conclude = useConcludePlan()

  // Les deux issues sont irreversibles : la tournee quitte le brouillon dans
  // les deux cas, et l'annulation rend les commandes au pool.
  const [deciding, setDeciding] = useState<'validate' | 'cancel' | null>(null)
  const receiving = selected !== null && session.canReceive(selected, selected)

  const drafts = tours.filter((tour) => tour.status === 'draft')

  return (
    <div className="flex flex-col gap-3">
      <PlanningSessionBar
        userName={user === null ? '—' : `${user.firstName} ${user.lastName}`}
        selected={selected}
        awaitsConclusion={awaits}
        isPending={isPending || conclude.isPending}
        onValidate={() => setDeciding('validate')}
        onCancel={() => setDeciding('cancel')}
      />

      <ConfirmDialog
        open={deciding !== null}
        onOpenChange={(open) => (open ? undefined : setDeciding(null))}
        title={t(deciding === 'cancel' ? 'planning.cancelPlanTitle' : 'planning.validatePlanTitle')}
        description={
          deciding === 'cancel'
            ? t('planning.cancelPlanBody', {
                count: (selected?.stops ?? []).flatMap((stop) => stop.orderServiceIds ?? []).length,
              })
            : t('planning.validatePlanBody')
        }
        confirmLabel={t(deciding === 'cancel' ? 'planning.cancelPlan' : 'planning.validate')}
        variant={deciding === 'cancel' ? 'destructive' : 'default'}
        isPending={conclude.isPending}
        onConfirm={() => {
          if (selected !== null) {
            if (deciding === 'cancel') conclude.cancel(selected)
            else conclude.validate(selected)
          }

          setDeciding(null)
        }}
      />

      <div className="grid gap-3 xl:grid-cols-[16rem_minmax(0,1fr)_18rem]">
        <section className="flex max-h-[70vh] flex-col gap-2 overflow-y-auto rounded-lg border bg-card p-2">
          <h2 className="text-sm font-semibold">{t('planning.plannedTours')}</h2>

          <PlannedToursPanel
            tours={tours}
            selectedTourId={selectedTourId}
            onSelectTour={onSelectTour}
            onFocusStop={aim}
            onUnplan={receiving ? onUnplan : undefined}
            // Tant que la tournee ouverte attend sa conclusion, les autres
            // sont fermees : deux plans en parallele se confondent.
            lockedTourId={awaits ? selectedTourId : null}
            isHeldByOther={session.heldByOther}
          />
        </section>

        {/* La hauteur vient d'ici : Leaflet mesure son conteneur au montage, et
            un parent de hauteur automatique le laisserait a zero pixel. */}
        <div className="h-[70vh]">
          <PlanningMap
            orders={orders}
            tours={tours}
            focus={focus}
            routeTrace={route.data ?? []}
            routeTourId={selectedTourId}
            className="min-h-0 w-full flex-1 rounded-lg border"
            onPlanOrder={receiving ? onPlanOrder : undefined}
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
            onPlan={receiving ? onPlanOrder : undefined}
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
