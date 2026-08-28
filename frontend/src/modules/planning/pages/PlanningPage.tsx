import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { useTourList } from '@/modules/tours/hooks/useTours'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import { PlanningMapScreen } from '../components/map/PlanningMapScreen'
import { PlanningModeSwitcher, type PlanningMode } from '../components/PlanningModeSwitcher'
import { PlanningPanels } from '../components/PlanningPanels'
import { planPayloadOf, type PlanningDragPayload } from '../dnd'
import { usePlanIntoTour, usePlanningPool, useUnplanFromTour } from '../hooks/usePlanning'
import type { PlanningRejection, PoolFilters } from '../types/pool'

/**
 * L'écran de planification.
 *
 * À gauche ce qui attend une tournée, à droite les brouillons du jour. On
 * choisit une tournée, puis on y verse une commande ou l'un de ses services.
 * La vue carte répond à l'autre question — ces commandes sont-elles voisines ?
 * — sur les **mêmes** brouillons : le §73 l'exige.
 *
 * **Le serveur décide, l'écran rapporte.** Le regroupement des arrêts, l'ordre
 * des chargements et les refus viennent de lui ; reproduire ces règles ici
 * donnerait deux vérités qui finiraient par diverger.
 */
export function PlanningPage() {
  const { t } = useTranslation()

  // Aucune date au depart : un pool ouvert sur le seul jour courant parait
  // vide des que rien n'est demande pour aujourd'hui, et on croit la recherche
  // cassee. La date sert a restreindre, pas a masquer.
  const [date, setDate] = useState('')
  const [search, setSearch] = useState('')
  const [selectedTourId, setSelectedTourId] = useState<string | null>(null)
  const [mode, setMode] = useState<PlanningMode>('panels')

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
    // La carte montre aussi les tournees deja confirmees : elles occupent le
    // terrain, et planifier sans les voir enverrait deux camions dans la meme
    // rue. Les panneaux, eux, ne recoivent que des brouillons.
    status: mode === 'map' ? undefined : 'draft',
    tourDate: date === '' ? undefined : date,
    // La carte trace l'ordre des arrets : sans eux, une tournee n'y est qu'un
    // nom. Les panneaux, eux, n'en ont pas l'usage.
    withStops: mode === 'map',
    // La carte voit sa propre composition ; les panneaux, non.
    includePending: mode === 'map',
  })
  const plan = usePlanIntoTour()
  const unplan = useUnplanFromTour()

  const orders = pool.data?.data ?? []
  const visible = tours.data?.data ?? []

  // Seul un brouillon recoit : c'est lui qu'on choisit, quelle que soit la vue.
  const drafts = visible.filter((tour) => tour.status === 'draft')

  /** Rapporte ce que le serveur a fait, refus compris — il ne les invente pas. */
  const report = (planned: string[], rejected: PlanningRejection[]) => {
    if (planned.length > 0) {
      toast.success(t('planning.planned', { count: planned.length }))
    }

    for (const refusal of rejected) {
      toast.warning(t(`planning.rejected.${refusal.reason}`, { defaultValue: refusal.reason }))
    }
  }

  const sendTo = (
    tourId: string,
    payload: { orderIds?: string[]; orderServiceIds?: string[] },
  ) =>
    plan.mutate(
      { tourId, ...payload },
      { onSuccess: (result) => report(result.planned, result.rejected) },
    )

  const send = (payload: { orderIds?: string[]; orderServiceIds?: string[] }) => {
    if (selectedTourId === null) return

    sendTo(selectedTourId, payload)
  }

  const drop = (tourId: string, drag: PlanningDragPayload) => sendTo(tourId, planPayloadOf(drag))

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('planning.title')}
        description={t('planning.subtitle')}
        actions={<PlanningModeSwitcher mode={mode} onChange={setMode} />}
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

      {mode === 'map' ? (
        <PlanningMapScreen
          orders={orders}
          tours={visible}
          search={search}
          onSearchChange={setSearch}
          selectedTourId={selectedTourId}
          onSelectTour={setSelectedTourId}
          onPlanOrder={(orderId) => send({ orderIds: [orderId] })}
          isPending={plan.isPending}
          onUnplan={(tourId, orderServiceIds) =>
            unplan.mutate(
              { tourId, orderServiceIds },
              {
                onSuccess: (result) => {
                  if (result.unplanned.length > 0) {
                    toast.success(t('planning.unplanned', { count: result.unplanned.length }))
                  }

                  report([], result.rejected)
                },
              },
            )
          }
        />
      ) : (
        <PlanningPanels
          orders={orders}
          drafts={drafts}
          poolState={{
            error: pool.error,
            isPending: pool.isPending,
            refetch: () => void pool.refetch(),
          }}
          toursState={{
            error: tours.error,
            isPending: tours.isPending,
            refetch: () => void tours.refetch(),
          }}
          filtered={date !== '' || search !== ''}
          isPending={plan.isPending}
          selectedTourId={selectedTourId}
          onSelectTour={setSelectedTourId}
          onPlan={send}
          onPlanDrop={drop}
        />
      )}

      <p className="text-xs text-muted-foreground">
        <StatusBadge status="draft" source="tour" /> {t('planning.draftNotice')}
      </p>
    </div>
  )
}
