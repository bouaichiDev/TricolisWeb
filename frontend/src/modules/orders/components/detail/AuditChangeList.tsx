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
 *
 * Le journal parle en colonnes et en identifiants. `fieldLabel` et
 * `formatValue` permettent à l'appelant, qui connaît l'élément, de dire
 * « Client » plutôt que `customer_id`, et son nom plutôt que son ULID.
 */
export interface AuditChangeListProps {
  logs: AuditLog[]
  fieldLabel?: (key: string) => string
  formatValue?: (key: string, value: unknown) => string
}

export function AuditChangeList({
  logs,
  fieldLabel = (key) => key,
  formatValue = (_, value) => formatAuditValue(value),
}: AuditChangeListProps) {
  const { t } = useTranslation()

  // `claim.created` se lit comme `created` : le préfixe nomme l'entité, que
  // l'écran montre déjà.
  const actionLabel = (action: string) =>
    t(`auditActions.${action}`, {
      defaultValue: t(`auditActions.${action.split('.').pop()}`, { defaultValue: action }),
    })

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
              {actionLabel(log.action)}
            </Badge>
            <span className="font-mono text-xs text-muted-foreground">
              {formatDateTime(log.createdAt)}
            </span>
          </div>

          {changes.length > 0 ? (
            <ul className="mt-1 flex flex-col gap-0.5 text-xs">
              {changes.map((change) => (
                <li key={change.key} className="flex flex-wrap gap-2">
                  <span className="font-medium">{fieldLabel(change.key)}</span>
                  <span className="text-muted-foreground">
                    {formatValue(change.key, change.before)} →{' '}
                    {formatValue(change.key, change.after)}
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
