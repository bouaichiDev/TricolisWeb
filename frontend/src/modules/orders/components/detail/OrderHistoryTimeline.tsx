import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { diffAuditValues, formatAuditValue } from '@/modules/audit/types/auditLog'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { useOrderHistory } from '../../hooks/useOrders'

/**
 * Historique de la commande.
 *
 * `GET /orders/{order}/history` renvoie le journal d'audit filtré sur la
 * commande : ce n'est pas un historique reconstruit côté client, et l'écran
 * n'en déduit rien de plus que ce que le journal contient.
 *
 * Chaque entrée n'affiche que les champs réellement modifiés : le journal
 * enregistre deux instantanés complets, les montrer entiers noierait la
 * modification sous les colonnes inchangées.
 */
export function OrderHistoryTimeline({ orderId }: { orderId: string }) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const history = useOrderHistory(orderId, page)

  if (history.isPending) return <ListSkeleton />
  if (history.error) {
    return <ErrorState error={history.error} onRetry={() => void history.refetch()} />
  }

  const logs = history.data?.data ?? []
  const meta = history.data?.meta

  return (
    <SectionCard title={t('orders.history.title')}>
      {logs.length === 0 ? (
        <EmptyState title={t('orders.history.empty')} />
      ) : (
        <ol className="flex flex-col gap-4">
          {logs.map((log) => {
            const changes = diffAuditValues(log)

            return (
              <li key={log.id} className="border-l-2 pl-4">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge variant="outline">
                    {t(`auditActions.${log.action}`, { defaultValue: log.action })}
                  </Badge>
                  <span className="text-xs text-muted-foreground">
                    {formatDateTime(log.createdAt)}
                  </span>
                </div>

                {changes.length > 0 ? (
                  <ul className="mt-2 flex flex-col gap-1 text-sm">
                    {changes.map((change) => (
                      <li key={change.key} className="flex flex-wrap gap-2">
                        <span className="font-medium">{change.key}</span>
                        <span className="text-muted-foreground">
                          {formatAuditValue(change.before)} → {formatAuditValue(change.after)}
                        </span>
                      </li>
                    ))}
                  </ul>
                ) : null}
              </li>
            )
          })}
        </ol>
      )}

      {meta && meta.lastPage > 1 ? (
        <div className="mt-4 flex items-center justify-between gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={page <= 1}
            onClick={() => setPage((current) => current - 1)}
          >
            {t('common.previous')}
          </Button>

          <span className="text-xs text-muted-foreground">
            {page} / {meta.lastPage}
          </span>

          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={page >= meta.lastPage}
            onClick={() => setPage((current) => current + 1)}
          >
            {t('common.next')}
          </Button>
        </div>
      ) : null}
    </SectionCard>
  )
}
