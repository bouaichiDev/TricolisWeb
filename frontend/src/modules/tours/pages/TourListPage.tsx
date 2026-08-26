import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'

import { LayoutGrid, List, Plus } from 'lucide-react'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { PlanningPoolSidebar } from '@/modules/planning/components/PlanningPoolSidebar'
import { planPayloadOf, type PlanningDragPayload } from '@/modules/planning/dnd'
import { usePlanIntoTour } from '@/modules/planning/hooks/usePlanning'
import type { PlanningRejection } from '@/modules/planning/types/pool'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { DataTable } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { Button } from '@/shared/components/ui/button'

import { TourBoard } from '../components/TourBoard'
import { useTourColumns } from '../components/useTourColumns'
import { useTourList } from '../hooks/useTours'
import type { TourFilters } from '../types/tour'

/** Liste des tournées, tous états confondus. */
export function TourListPage() {
  const { t } = useTranslation()

  // Deux lectures d'une meme liste : le tableau pour retrouver une tournee,
  // les colonnes pour les comparer entre elles. Les arrets ne sont demandes
  // que par la seconde, qui seule les montre.
  const [view, setView] = useState<'list' | 'board'>('board')

  const [filters, setFilters] = useState<TourFilters>({
    page: 1,
    perPage: 25,
    sort: 'tour_date',
    direction: 'desc',
  })

  const { data, isPending, error, refetch } = useTourList({
    ...filters,
    withStops: view === 'board',
  })

  const patch = (next: Partial<TourFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const plan = usePlanIntoTour()

  /** Rapporte ce que le serveur a fait, refus compris — il ne les invente pas. */
  const report = (planned: string[], rejected: PlanningRejection[]) => {
    if (planned.length > 0) {
      toast.success(t('planning.planned', { count: planned.length }))
    }

    for (const refusal of rejected) {
      toast.warning(t(`planning.rejected.${refusal.reason}`, { defaultValue: refusal.reason }))
    }
  }

  const send = (tourId: string, payload: { orderIds?: string[]; orderServiceIds?: string[] }) =>
    plan.mutate(
      { tourId, ...payload },
      { onSuccess: (result) => report(result.planned, result.rejected) },
    )

  const drop = (tourId: string, drag: PlanningDragPayload) => send(tourId, planPayloadOf(drag))

  // Une seule tournee brouillon : le bouton du pool sait ou verser sans qu'on
  // ait a la designer. Au-dela, seul le glisser tranche, et les boutons se
  // retirent plutot que de choisir a la place du planificateur.
  const drafts = (data?.data ?? []).filter((tour) => tour.status === 'draft')
  const soleDraft = drafts.length === 1 ? drafts[0] : undefined

  const columns = useTourColumns()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('tours.title')}
        description={t('tours.subtitle')}
        actions={
          <span className="flex gap-1">
            <Button
              type="button"
              variant={view === 'board' ? 'secondary' : 'ghost'}
              size="icon"
              title={t('tours.viewBoard')}
              aria-label={t('tours.viewBoard')}
              aria-pressed={view === 'board'}
              onClick={() => setView('board')}
            >
              <LayoutGrid className="size-4" aria-hidden />
            </Button>
            <Button
              type="button"
              variant={view === 'list' ? 'secondary' : 'ghost'}
              size="icon"
              title={t('tours.viewList')}
              aria-label={t('tours.viewList')}
              aria-pressed={view === 'list'}
              onClick={() => setView('list')}
            >
              <List className="size-4" aria-hidden />
            </Button>

            <PermissionGuard permission="tours.create">
              <Button asChild className="ml-2">
                <Link to="/tours/create">
                  <Plus className="size-4" aria-hidden />
                  {t('tours.create')}
                </Link>
              </Button>
            </PermissionGuard>
          </span>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(search) => patch({ search: search || undefined })}
        />

        <StatusFilterSelect
          source="tour"
          value={filters.status}
          onChange={(status) => patch({ status })}
        />
      </div>

      {view === 'board' ? (
        <div className="flex gap-4">
          <div className="min-w-0 flex-1">
            {error ? (
              <ErrorState error={error} onRetry={() => void refetch()} />
            ) : isPending ? (
              <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
            ) : (
              <TourBoard
                tours={data?.data ?? []}
                emptyMessage={t('tours.empty')}
                onPlanDrop={drop}
              />
            )}
          </div>

          <PlanningPoolSidebar
            requestedDate={filters.tourDate}
            isPending={plan.isPending}
            onPlan={soleDraft === undefined ? undefined : (payload) => send(soleDraft.id, payload)}
          />
        </div>
      ) : (
      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        emptyMessage={t('tours.empty')}
      />
      )}
    </div>
  )
}
