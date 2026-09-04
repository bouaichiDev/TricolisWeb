import { useTranslation } from 'react-i18next'

import { diffAuditValues, formatAuditValue, type AuditLog } from '@/modules/audit/types/auditLog'
import { Badge } from '@/shared/components/ui/badge'
import { formatDateTime } from '@/shared/utils/format'

/**
 * Les modifications enregistrées sur un élément, champ par champ.
 *
 * Complète le parcours de statuts : celui-ci dit *où en est* l'élément, celle-ci
 * dit *ce qui a changé*. Le journal enregistre deux instantanés complets ; seuls
 * les champs réellement modifiés sont montrés, sinon la modification se perdrait
 * sous les colonnes inchangées.
 */
export function AuditChangeList({ logs }: { logs: AuditLog[] }) {
  const { t } = useTranslation()

  const withChanges = logs
    .map((log) => ({ log, changes: diffAuditValues(log) }))
    .filter((entry) => entry.changes.length > 0 || entry.log.action !== 'updated')

  if (withChanges.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('orders.entityHistory.empty')}</p>
  }

  return (
    <ol className="flex flex-col gap-3">
      {withChanges.map(({ log, changes }) => (
        <li key={log.id} className="border-l-2 pl-3">
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="outline">
              {t(`auditActions.${log.action}`, { defaultValue: log.action })}
            </Badge>
            <span className="font-mono text-xs text-muted-foreground">
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
      ))}
    </ol>
  )
}
