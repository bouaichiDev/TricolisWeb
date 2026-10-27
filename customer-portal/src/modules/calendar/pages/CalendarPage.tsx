import { useState } from 'react'

import { errorMessage } from '@/shared/api/errors'
import { ErrorState } from '@/shared/components/feedback/States'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'

import { CalendarFiltersBar } from '../components/CalendarFiltersBar'
import { CalendarStatusLegend } from '../components/CalendarStatusLegend'
import { OrderEventSheet } from '../components/OrderEventSheet'
import { OrdersCalendar } from '../components/OrdersCalendar'
import { useCalendarFilters } from '../hooks/useCalendarFilters'
import { useCalendarOrders } from '../hooks/useCalendarOrders'
import type { CalendarOrderEvent, CalendarRange } from '../types'

/**
 * Calendrier des commandes (§36) : filtres, FullCalendar, vue rapide.
 *
 * La date d'un événement est tranchée par le backend — planification d'abord,
 * date demandée sinon — et la période chargée est celle que FullCalendar
 * affiche, jamais davantage.
 */
export function CalendarPage() {
  const [range, setRange] = useState<CalendarRange | null>(null)
  const [selected, setSelected] = useState<CalendarOrderEvent | null>(null)
  const { filters, update, reset } = useCalendarFilters()
  const orders = useCalendarOrders(range, filters)

  const changeRange = (next: CalendarRange) => {
    setRange((current) => (current?.from === next.from && current.to === next.to ? current : next))
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Calendrier"
        description="Vos commandes à la date planifiée par votre transporteur, ou à la date demandée tant qu’elles ne sont pas planifiées."
      />

      <CalendarFiltersBar filters={filters} options={orders.data?.meta.filters} onChange={update} onReset={reset} />

      {orders.isError ? (
        <ErrorState message={errorMessage(orders.error)} onRetry={() => void orders.refetch()} />
      ) : (
        <>
          {orders.data?.meta.truncated && (
            <Alert>
              <AlertDescription>
                Trop de commandes sur cette période : seules les {orders.data.meta.count} premières sont affichées. Affinez les filtres ou passez à la vue semaine.
              </AlertDescription>
            </Alert>
          )}
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-muted-foreground text-sm" role="status" aria-live="polite">
              {orders.isFetching ? 'Chargement des commandes…' : `${orders.data?.meta.count ?? 0} commande(s) sur la période`}
            </p>
            <CalendarStatusLegend events={orders.data?.data ?? []} />
          </div>
          <OrdersCalendar events={orders.data?.data ?? []} onRangeChange={changeRange} onEventClick={setSelected} />
        </>
      )}

      <OrderEventSheet event={selected} onClose={() => setSelected(null)} />
    </div>
  )
}
