import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'

import { PermissionGuard } from '@/app/guards/PermissionGuard'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { useTourList } from '@/modules/tours/hooks/useTours'

import { PoolOrderCard } from '../components/PoolOrderCard'
import { TourDraftPanel } from '../components/TourDraftPanel'
import { usePlanIntoTour, usePlanningPool } from '../hooks/usePlanning'
import type { PlanningRejection, PoolFilters } from '../types/pool'

/**
 * L'écran de planification.
 *
 * À gauche ce qui attend une tournée, à droite les brouillons du jour. On
 * choisit une tournée, puis on y verse une commande ou l'un de ses services.
 *
 * **Le serveur décide, l'écran rapporte.** Le regroupement des arrêts, l'ordre
 * des chargements et les refus viennent de lui ; reproduire ces règles ici
 * donnerait deux vérités qui finiraient par diverger.
 *
 * Le glisser-déposer viendra par-dessus cette même mutation : ce sont deux
 * façons de dire la même chose au serveur, et il vaut mieux que la seconde
 * arrive sur un écran qui fonctionne déjà.
 */
export function PlanningPage() {
  const { t } = useTranslation()

  // Aucune date au depart : un pool ouvert sur le seul jour courant parait
  // vide des que rien n'est demande pour aujourd'hui, et on croit la recherche
  // cassee. La date sert a restreindre, pas a masquer.
  const [date, setDate] = useState('')
  const [search, setSearch] = useState('')
  const [selectedTourId, setSelectedTourId] = useState<string | null>(null)

  const filters: PoolFilters = {
    page: 1,
    perPage: 50,
    requestedDate: date === '' ? undefined : date,
    search: search === '' ? undefined : search,
  }

  const pool = usePlanningPool(filters)
  const tours = useTourList({
    page: 1,
    perPage: 50,
    status: 'draft',
    tourDate: date === '' ? undefined : date,
  })
  const plan = usePlanIntoTour()

  const orders = pool.data?.data ?? []
  const drafts = tours.data?.data ?? []

  /** Rapporte ce que le serveur a fait, refus compris — il ne les invente pas. */
  const report = (planned: string[], rejected: PlanningRejection[]) => {
    if (planned.length > 0) {
      toast.success(t('planning.planned', { count: planned.length }))
    }

    for (const refusal of rejected) {
      toast.warning(t(`planning.rejected.${refusal.reason}`, { defaultValue: refusal.reason }))
    }
  }

  const send = (payload: { orderIds?: string[]; orderServiceIds?: string[] }) => {
    if (selectedTourId === null) return

    plan.mutate(
      { tourId: selectedTourId, ...payload },
      { onSuccess: (result) => report(result.planned, result.rejected) },
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('planning.title')}
        description={t('planning.subtitle')}
        actions={
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

      <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div className="flex flex-col gap-2">
          <Label htmlFor="planning-date">{t('planning.date')}</Label>
          <Input
            id="planning-date"
            type="date"
            value={date}
            onChange={(event) => setDate(event.target.value)}
            className="w-48"
          />
        </div>

        <SearchInput value={search} onChange={setSearch} />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <SectionCard title={t('planning.pool')} description={t('planning.poolHint')}>
          {pool.error ? (
            <ErrorState error={pool.error} onRetry={() => void pool.refetch()} />
          ) : pool.isPending ? (
            <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
          ) : orders.length === 0 ? (
            <EmptyState
              title={t('planning.poolEmpty')}
              description={
                date === '' && search === ''
                  ? t('planning.poolEmptyHint')
                  : t('planning.poolEmptyFiltered')
              }
            />
          ) : (
            <ul className="flex flex-col gap-2">
              {orders.map((order) => (
                <PoolOrderCard
                  key={order.id}
                  order={order}
                  isPending={plan.isPending}
                  onPlanOrder={
                    selectedTourId === null ? null : () => send({ orderIds: [order.id] })
                  }
                  onPlanService={
                    selectedTourId === null
                      ? null
                      : (orderServiceId) => send({ orderServiceIds: [orderServiceId] })
                  }
                />
              ))}
            </ul>
          )}
        </SectionCard>

        <SectionCard title={t('planning.drafts')} description={t('planning.draftsHint')}>
          {tours.error ? (
            <ErrorState error={tours.error} onRetry={() => void tours.refetch()} />
          ) : tours.isPending ? (
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
                  onSelect={() => setSelectedTourId(tour.id)}
                />
              ))}
            </ul>
          )}

          {selectedTourId === null && drafts.length > 0 ? (
            <p className="mt-3 text-sm text-muted-foreground">{t('planning.pickTour')}</p>
          ) : null}
        </SectionCard>
      </div>

      <p className="text-xs text-muted-foreground">
        <StatusBadge status="draft" source="tour" /> {t('planning.draftNotice')}
      </p>
    </div>
  )
}
