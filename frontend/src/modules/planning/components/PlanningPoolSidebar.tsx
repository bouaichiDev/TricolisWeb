import { PanelRightClose, PanelRightOpen } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { SearchInput } from '@/shared/components/data/SearchInput'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { Button } from '@/shared/components/ui/button'

import { PoolOrderCard } from './PoolOrderCard'
import { usePlanningPool } from '../hooks/usePlanning'
import type { PoolFilters } from '../types/pool'

interface PlanningPoolSidebarProps {
  /** Date des tournées affichées à côté, pour n'offrir que ce qui les concerne. */
  requestedDate?: string
  isPending: boolean
  /** Absent tant qu'aucune tournée ne peut recevoir : les boutons disparaîssent. */
  onPlan?: (payload: { orderIds?: string[]; orderServiceIds?: string[] }) => void
}

/**
 * Le pool des commandes, en panneau latéral repliable.
 *
 * Il accompagne les tournées en colonnes : on tient d'un côté ce qui attend,
 * de l'autre ce qui peut le prendre. Replié, il rend sa largeur aux colonnes —
 * un planificateur qui compare huit tournées n'a pas besoin du pool sous les
 * yeux en permanence.
 *
 * Le repli est **local à l'écran** : ce n'est pas un réglage, c'est un geste,
 * et il n'a pas à survivre à la visite.
 */
export function PlanningPoolSidebar({
  requestedDate,
  isPending,
  onPlan,
}: PlanningPoolSidebarProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(true)
  const [search, setSearch] = useState('')

  const filters: PoolFilters = {
    page: 1,
    perPage: 50,
    requestedDate,
    search: search === '' ? undefined : search,
  }

  const pool = usePlanningPool(filters)
  const orders = pool.data?.data ?? []

  if (!open) {
    return (
      <div className="shrink-0">
        <Button
          type="button"
          variant="outline"
          size="icon"
          title={t('planning.showPool')}
          aria-label={t('planning.showPool')}
          aria-expanded={false}
          onClick={() => setOpen(true)}
        >
          <PanelRightOpen className="size-4" aria-hidden />
        </Button>
      </div>
    )
  }

  return (
    <aside className="flex w-80 shrink-0 flex-col gap-3 rounded-lg border bg-card p-3">
      <div className="flex items-center justify-between gap-2">
        <h2 className="text-sm font-semibold">{t('planning.pool')}</h2>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          title={t('planning.hidePool')}
          aria-label={t('planning.hidePool')}
          aria-expanded
          onClick={() => setOpen(false)}
        >
          <PanelRightClose className="size-4" aria-hidden />
        </Button>
      </div>

      <SearchInput value={search} onChange={setSearch} label={t('planning.searchPool')} />

      <p className="text-xs text-muted-foreground">{t('planning.dragHint')}</p>

      {pool.error ? (
        <ErrorState error={pool.error} onRetry={() => void pool.refetch()} />
      ) : pool.isPending ? (
        <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
      ) : orders.length === 0 ? (
        <EmptyState
          title={t('planning.poolEmpty')}
          description={search === '' ? t('planning.poolEmptyHint') : t('planning.poolEmptyFiltered')}
        />
      ) : (
        <ul className="flex max-h-[70vh] flex-col gap-2 overflow-y-auto pr-1">
          {orders.map((order) => (
            <PoolOrderCard
              key={order.id}
              order={order}
              draggable
              isPending={isPending}
              onPlanOrder={onPlan === undefined ? null : () => onPlan({ orderIds: [order.id] })}
              onPlanService={
                onPlan === undefined
                  ? null
                  : (orderServiceId) => onPlan({ orderServiceIds: [orderServiceId] })
              }
            />
          ))}
        </ul>
      )}
    </aside>
  )
}
