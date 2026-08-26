import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import type { Tour } from '@/modules/tours/types/tour'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import { PoolOrderCard } from './PoolOrderCard'
import { TourDraftPanel } from './TourDraftPanel'
import type { PoolOrder } from '../types/pool'

/** L'état d'une requête, réduit à ce que le panneau en montre. */
interface QueryState {
  error: Error | null
  isPending: boolean
  refetch: () => void
}

interface PlanningPanelsProps {
  orders: PoolOrder[]
  drafts: Tour[]
  poolState: QueryState
  toursState: QueryState
  /** Vrai dès qu'un filtre est posé : le vide ne se raconte pas pareil. */
  filtered: boolean
  isPending: boolean
  selectedTourId: string | null
  onSelectTour: (tourId: string) => void
  onPlan: (payload: { orderIds?: string[]; orderServiceIds?: string[] }) => void
}

/**
 * La planification en deux panneaux : ce qui attend, ce qui peut le prendre.
 *
 * On choisit d'abord une tournée, ensuite on y verse. Les boutons de
 * planification n'apparaissent pas avant ce choix — ils n'auraient nulle part
 * où verser.
 */
export function PlanningPanels({
  orders,
  drafts,
  poolState,
  toursState,
  filtered,
  isPending,
  selectedTourId,
  onSelectTour,
  onPlan,
}: PlanningPanelsProps) {
  const { t } = useTranslation()

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <SectionCard title={t('planning.pool')} description={t('planning.poolHint')}>
        {poolState.error ? (
          <ErrorState error={poolState.error} onRetry={poolState.refetch} />
        ) : poolState.isPending ? (
          <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
        ) : orders.length === 0 ? (
          <EmptyState
            title={t('planning.poolEmpty')}
            description={filtered ? t('planning.poolEmptyFiltered') : t('planning.poolEmptyHint')}
          />
        ) : (
          <ul className="flex flex-col gap-2">
            {orders.map((order) => (
              <PoolOrderCard
                key={order.id}
                order={order}
                isPending={isPending}
                onPlanOrder={selectedTourId === null ? null : () => onPlan({ orderIds: [order.id] })}
                onPlanService={
                  selectedTourId === null
                    ? null
                    : (orderServiceId) => onPlan({ orderServiceIds: [orderServiceId] })
                }
              />
            ))}
          </ul>
        )}
      </SectionCard>

      <SectionCard title={t('planning.drafts')} description={t('planning.draftsHint')}>
        {toursState.error ? (
          <ErrorState error={toursState.error} onRetry={toursState.refetch} />
        ) : toursState.isPending ? (
          <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
        ) : drafts.length === 0 ? (
          <EmptyState
            title={t('planning.noDraft')}
            description={t('planning.noDraftHint')}
            action={
              <PermissionGuard permission="tours.create">
                <Button asChild>
                  <Link to="/tours/create">
                    <Plus className="size-4" aria-hidden />
                    {t('tours.create')}
                  </Link>
                </Button>
              </PermissionGuard>
            }
          />
        ) : (
          <ul className="flex flex-col gap-2">
            {drafts.map((tour) => (
              <TourDraftPanel
                key={tour.id}
                tour={tour}
                selected={tour.id === selectedTourId}
                onSelect={() => onSelectTour(tour.id)}
              />
            ))}
          </ul>
        )}

        {selectedTourId === null && drafts.length > 0 ? (
          <p className="mt-3 text-sm text-muted-foreground">{t('planning.pickTour')}</p>
        ) : null}
      </SectionCard>
    </div>
  )
}
