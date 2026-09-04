import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { LayoutGrid, List, Plus } from 'lucide-react'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { PlanningPoolSidebar } from '@/modules/planning/components/PlanningPoolSidebar'
import { DataTable } from '@/shared/components/data/DataTable'
import { BusyOverlay } from '@/shared/components/feedback/BusyOverlay'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { Button } from '@/shared/components/ui/button'

import { TourBoard } from '../components/TourBoard'
import { ALL_CUSTOMERS, TourFilterBar, todayIso } from '../components/TourFilterBar'
import { TourEditDialog } from '../components/TourEditDialog'
import { TourMapDialog } from '../components/TourMapDialog'
import { useTourColumns } from '../components/useTourColumns'
import { useBoardPlanning } from '../hooks/useBoardPlanning'
import { useTourList } from '../hooks/useTours'
import type { Tour, TourFilters } from '../types/tour'

/** Liste des tournées, tous états confondus. */
export function TourListPage() {
  const { t } = useTranslation()

  // Deux lectures d'une meme liste : le tableau pour retrouver une tournee,
  // les colonnes pour les comparer entre elles. Les arrets ne sont demandes
  // que par la seconde, qui seule les montre.
  const [view, setView] = useState<'list' | 'board'>('board')

  // La date est obligatoire : une tournee se lit par jour, et une liste sans
  // date melangerait un mois de preparation. Le jour courant au depart.
  const [filters, setFilters] = useState<TourFilters>({
    page: 1,
    perPage: 25,
    sort: 'tour_date',
    direction: 'desc',
    tourDate: todayIso(),
  })

  // Le client ne filtre que le pool : une tournee en dessert plusieurs, et le
  // serveur n'expose aucun filtre client sur `/tours`.
  const [customerId, setCustomerId] = useState(ALL_CUSTOMERS)

  const { data, isPending, error, refetch } = useTourList({
    ...filters,
    withStops: view === 'board',
  })

  const patch = (next: Partial<TourFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const planning = useBoardPlanning()

  // Une seule tournee brouillon : le bouton du pool sait ou verser sans qu'on
  // ait a la designer. Au-dela, seul le glisser tranche, et les boutons se
  // retirent plutot que de choisir a la place du planificateur.
  const drafts = (data?.data ?? []).filter((tour) => tour.status === 'draft')
  const soleDraft = drafts.length === 1 ? drafts[0] : undefined

  const columns = useTourColumns()

  // La tournee dont on regarde le trace ; nulle quand la carte est fermee.
  const [mapped, setMapped] = useState<Tour | null>(null)

  // La tournee en cours de modification ; nulle quand la fenetre est fermee.
  const [edited, setEdited] = useState<Tour | null>(null)

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

      <TourFilterBar
        date={filters.tourDate ?? todayIso()}
        onDateChange={(tourDate) => patch({ tourDate })}
        customerId={customerId}
        onCustomerChange={setCustomerId}
        search={filters.search ?? ''}
        onSearchChange={(search) => patch({ search: search || undefined })}
        status={filters.status}
        onStatusChange={(status) => patch({ status })}
      />

      {view === 'board' ? (
        <div className="relative flex gap-4">
          {/* Planifier n'est pas instantane : le serveur regroupe, promeut le
              chargement au depot et recalcule. Un second clic verserait deux
              fois. */}
          <BusyOverlay active={planning.isPending} label={t('planning.working')} />

          <div className="min-w-0 flex-1">
            {error ? (
              <ErrorState error={error} onRetry={() => void refetch()} />
            ) : isPending ? (
              <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
            ) : (
              <TourBoard
                tours={data?.data ?? []}
                emptyMessage={t('tours.empty')}
                onPlanDrop={planning.drop}
                onUnplan={planning.release}
                onShowMap={setMapped}
                onEdit={setEdited}
              />
            )}
          </div>

          <PlanningPoolSidebar
            requestedDate={filters.tourDate}
            customerId={customerId === ALL_CUSTOMERS ? undefined : customerId}
            isPending={planning.isPending}
            onPlan={
              soleDraft === undefined ? undefined : (payload) => planning.send(soleDraft.id, payload)
            }
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

      {/* La carte d'une tournee est l'ecran de planification entier : on y vient
          pour arbitrer entre tournees, pas pour en regarder une seule. */}
      <TourMapDialog
        tour={mapped}
        tours={data?.data ?? []}
        date={filters.tourDate}
        onClose={() => setMapped(null)}
      />
      <TourEditDialog tour={edited} onClose={() => setEdited(null)} />
    </div>
  )
}
