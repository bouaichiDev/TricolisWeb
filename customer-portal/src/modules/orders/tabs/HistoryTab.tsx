import { useState } from 'react'

import { errorMessage } from '@/shared/api/errors'
import { EmptyState, ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { Button } from '@/shared/components/ui/button'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDateTime } from '@/shared/utils/format'

import { useOrderHistory } from '../hooks/useOrders'

/** Historique fonctionnel : créée, modifiée, statut changé, dupliquée. */
export function HistoryTab({ orderId }: { orderId: string }) {
  const [page, setPage] = useState(1)
  const history = useOrderHistory(orderId, page, true)
  const meta = history.data?.meta

  return (
    <SectionCard title="Historique" description="Les grandes étapes de la commande.">
      <div className="space-y-4">
        {history.isPending && <InlineLoader />}
        {history.isError && <ErrorState message={errorMessage(history.error)} />}
        {history.data?.data.length === 0 && <EmptyState title="Aucun événement." />}
        <ol className="space-y-3">
          {history.data?.data.map((entry) => (
            <li key={entry.id} className="border-primary border-l-2 pl-3 text-sm">
              <p className="font-medium">
                {entry.label}
                {entry.status && ` : ${entry.status.from ?? '—'} → ${entry.status.to ?? '—'}`}
              </p>
              <p className="text-muted-foreground text-xs">
                {formatDateTime(entry.at)} · {entry.actor}
              </p>
            </li>
          ))}
        </ol>
        {meta && meta.lastPage > 1 && (
          <div className="flex justify-end gap-2">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>Plus récents</Button>
            <Button variant="outline" size="sm" disabled={page >= meta.lastPage} onClick={() => setPage(page + 1)}>Plus anciens</Button>
          </div>
        )}
      </div>
    </SectionCard>
  )
}
