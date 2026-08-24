import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTablePagination } from '@/shared/components/data/DataTablePagination'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { Button } from '@/shared/components/ui/button'

import { NewTrackingEventDialog } from './NewTrackingEventDialog'
import { TrackingEventCard } from './TrackingEventCard'
import { TrackingEventDetailDrawer } from './TrackingEventDetailDrawer'
import { useOrderTracking } from '../hooks/useTracking'
import type { TrackingEvent } from '../types/trackingEvent'

interface OrderTrackingTabProps {
  orderId: string
  /** Faux tant que l'onglet n'a pas été ouvert : la requête attend. */
  active: boolean
}

/**
 * Suivi d'exécution d'une commande, en journal chronologique.
 *
 * Le tri est **demandé au serveur** — `occurred_at` décroissant, le plus récent
 * en tête. Trier une page de vingt-cinq événements dans le navigateur donnerait
 * un ordre faux dès la seconde page.
 *
 * Rien ne se modifie ni ne s'efface ici : `tracking-events` n'expose ni `update`
 * ni `destroy`, et le module n'a que les permissions `view` et `create`. Un
 * journal se complète, il ne se corrige pas.
 */
export function OrderTrackingTab({ orderId, active }: OrderTrackingTabProps) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const [opened, setOpened] = useState<TrackingEvent | null>(null)
  const [creating, setCreating] = useState(false)

  const { data, isPending, error, refetch } = useOrderTracking(
    orderId,
    { page, perPage: 25, sort: 'occurred_at', direction: 'desc' },
    active,
  )

  const events = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="font-semibold">{t('tracking.title')}</p>
          <p className="text-sm text-muted-foreground">{t('tracking.description')}</p>
        </div>

        <PermissionGuard permission="tracking_events.create">
          <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
            <Plus className="size-4" aria-hidden />
            {t('tracking.add')}
          </Button>
        </PermissionGuard>
      </div>

      {error ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : isPending ? (
        <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
      ) : events.length === 0 ? (
        <EmptyState title={t('tracking.empty')} description={t('tracking.emptyHint')} />
      ) : (
        <ol className="flex flex-col">
          {events.map((event) => (
            <TrackingEventCard key={event.id} event={event} onOpen={() => setOpened(event)} />
          ))}
        </ol>
      )}

      {meta ? <DataTablePagination meta={meta} onPageChange={setPage} /> : null}

      <TrackingEventDetailDrawer event={opened} onClose={() => setOpened(null)} />

      {creating ? (
        <NewTrackingEventDialog orderId={orderId} open onOpenChange={setCreating} />
      ) : null}
    </div>
  )
}
