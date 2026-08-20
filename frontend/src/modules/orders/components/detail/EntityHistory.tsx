import { History } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useAuditLogs } from '@/modules/audit/hooks/useAuditLogs'
import { diffAuditValues, formatAuditValue } from '@/modules/audit/types/auditLog'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { usePermission } from '@/shared/hooks/usePermission'
import { formatDateTime } from '@/shared/utils/format'

interface EntityHistoryProps {
  /** Alias métier de `MorphMap` : `order_line`, `package`, `order_service`. */
  entityType: string
  entityId: string
  /** Déjà ouvert — dans un tiroir dédié, le repli n'aurait pas de sens. */
  defaultOpen?: boolean
}

/**
 * Historique d'un élément de la commande — création, modifications, statuts.
 *
 * Il n'existe pas de table d'historique : chaque écriture est journalisée dans
 * l'audit avec son type d'entité et son identifiant. `GET /audit-logs` accepte
 * précisément ces deux filtres, ce qui donne l'historique d'une ligne, d'un
 * colis ou d'un service sans route dédiée.
 *
 * Le journal demande `audit.view`. Sans cette permission l'écran le dit, plutôt
 * que d'afficher un bloc vide qu'on prendrait pour « rien ne s'est passé ».
 *
 * Replié par défaut, et **chargé seulement une fois ouvert** : une commande de
 * vingt lignes déclencherait autrement vingt requêtes à l'affichage de l'onglet.
 */
export function EntityHistory({
  entityType,
  entityId,
  defaultOpen = false,
}: EntityHistoryProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(defaultOpen)
  const canRead = usePermission('audit.view')

  const history = useAuditLogs({ page: 1, perPage: 25, entityType, entityId }, open && canRead)
  const logs = history.data?.data ?? []

  if (!canRead) {
    return <p className="text-xs text-muted-foreground">{t('orders.entityHistory.needsPermission')}</p>
  }

  return (
    <div className="flex flex-col gap-2">
      {defaultOpen ? null : (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="w-fit px-0"
          aria-expanded={open}
          onClick={() => setOpen((current) => !current)}
        >
          <History className="size-4" aria-hidden />
          {t('orders.entityHistory.show')}
        </Button>
      )}

      {open ? (
        history.isPending ? (
          <p className="text-xs text-muted-foreground">{t('common.loading')}</p>
        ) : logs.length === 0 ? (
          <p className="text-xs text-muted-foreground">{t('orders.entityHistory.empty')}</p>
        ) : (
          <ol className="flex flex-col gap-3">
            {logs.map((log) => {
              const changes = diffAuditValues(log)

              return (
                <li key={log.id} className="border-l-2 pl-3">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                      {t(`auditActions.${log.action}`, { defaultValue: log.action })}
                    </Badge>
                    <span className="text-xs text-muted-foreground">
                      {formatDateTime(log.createdAt)}
                    </span>
                  </div>

                  {changes.length > 0 ? (
                    <ul className="mt-1 flex flex-col gap-0.5 text-xs">
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
        )
      ) : null}
    </div>
  )
}
