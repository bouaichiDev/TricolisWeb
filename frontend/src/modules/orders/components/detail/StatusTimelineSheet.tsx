import { useTranslation } from 'react-i18next'

import { useAuditLogs } from '@/modules/audit/hooks/useAuditLogs'
import { useStatusList, useStatusTransitions } from '@/modules/statuses/hooks/useStatuses'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/shared/components/ui/sheet'
import { usePermission } from '@/shared/hooks/usePermission'

import { buildStatusTimeline } from '../../schemas/statusTimeline'
import { AuditChangeList } from './AuditChangeList'
import { StatusTimelineList } from './StatusTimelineList'

interface StatusTimelineSheetProps {
  /** Alias métier de `MorphMap` : `order_line`, `package`, `order_service`. */
  entityType: string
  entityId: string | null
  title?: string | null
  subtitle?: string | null
  /** Statut actuel de l'élément, pour savoir où l'on en est du parcours. */
  currentStatus?: string | null
  onClose: () => void
}

/**
 * Histoire d'un élément, en tiroir : son parcours, puis ses modifications.
 *
 * **Le parcours** croise deux sources de nature différente. Les statuts déjà
 * atteints se lisent dans le journal d'audit — un fait daté, avec son auteur.
 * Ceux à venir se lisent dans le référentiel : ce sont les transitions ouvertes
 * depuis le statut courant, donc une possibilité, pas une prévision.
 *
 * **Les modifications** répondent à l'autre question : non pas où en est
 * l'élément, mais ce qui a changé sur lui.
 *
 * Une entité dont personne n'a encore décrit le cycle de vie n'affiche que son
 * passé, et le dit plutôt que d'inventer des étapes.
 */
export function StatusTimelineSheet({
  entityType,
  entityId,
  title,
  subtitle,
  currentStatus,
  onClose,
}: StatusTimelineSheetProps) {
  const { t } = useTranslation()
  const open = entityId !== null
  const canReadAudit = usePermission('audit.view')

  const logs = useAuditLogs(
    { page: 1, perPage: 50, entityType, entityId: entityId ?? undefined },
    open && canReadAudit,
  )

  const referential = useStatusList(
    { page: 1, perPage: 100, source: entityType, sort: 'position', direction: 'asc' },
    open,
  )

  const known = new Map((referential.data?.data ?? []).map((status) => [status.code, status]))
  const current = currentStatus ? known.get(currentStatus) : undefined
  const transitions = useStatusTransitions(current?.id ?? '', open && current !== undefined)

  const reachable = (transitions.data ?? [])
    .map((transition) => transition.to)
    .filter((status): status is NonNullable<typeof status> => status !== undefined)

  const entries = buildStatusTimeline(logs.data?.data ?? [], currentStatus ?? null, reachable, known)
  const loading = logs.isPending || referential.isPending

  return (
    <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-md">
        <SheetHeader>
          <SheetTitle>{title ?? t('orders.statusTimeline.title')}</SheetTitle>
          <SheetDescription>{subtitle ?? t('orders.statusTimeline.title')}</SheetDescription>
        </SheetHeader>

        <div className="flex flex-col gap-5 px-4 pb-6">
          {!canReadAudit ? (
            <p className="text-sm text-muted-foreground">
              {t('orders.entityHistory.needsPermission')}
            </p>
          ) : loading ? (
            <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
          ) : (
            <>
              <section>
                <h3 className="mb-3 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('orders.statusTimeline.title')}
                </h3>

                {entries.length === 0 ? (
                  <p className="text-sm text-muted-foreground">
                    {t('orders.entityHistory.empty')}
                  </p>
                ) : (
                  <StatusTimelineList entries={entries} known={known} />
                )}

                {current === undefined ? (
                  <p className="mt-3 text-xs text-muted-foreground">
                    {t('orders.statusTimeline.noReferential')}
                  </p>
                ) : null}
              </section>

              <section className="border-t pt-4">
                <h3 className="mb-3 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('orders.statusTimeline.changes')}
                </h3>
                <AuditChangeList logs={logs.data?.data ?? []} />
              </section>
            </>
          )}
        </div>
      </SheetContent>
    </Sheet>
  )
}
