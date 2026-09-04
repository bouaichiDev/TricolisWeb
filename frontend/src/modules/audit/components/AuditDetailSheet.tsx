import { useTranslation } from 'react-i18next'

import { diffAuditValues, formatAuditValue, type AuditLog } from '../types/auditLog'
import { DetailField } from '@/shared/components/layout/DetailField'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/shared/components/ui/sheet'
import { formatDateTime } from '@/shared/utils/format'

interface AuditDetailSheetProps {
  log: AuditLog | null
  onClose: () => void
}

/**
 * Détail d'une entrée du journal.
 *
 * Seuls les champs qui ont réellement changé sont montrés : le journal stocke
 * deux instantanés complets, et les afficher entiers cacherait la modification
 * au milieu de dizaines de colonnes identiques.
 */
export function AuditDetailSheet({ log, onClose }: AuditDetailSheetProps) {
  const { t } = useTranslation()
  const changes = log ? diffAuditValues(log) : []

  return (
    <Sheet open={log !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
        {log ? (
          <>
            <SheetHeader>
              <SheetTitle>{t(`auditActions.${log.action}`, { defaultValue: log.action })}</SheetTitle>
              <SheetDescription>{formatDateTime(log.createdAt)}</SheetDescription>
            </SheetHeader>

            <div className="flex flex-col gap-6 px-4 pb-6">
              <dl className="grid gap-x-8 sm:grid-cols-2">
                <DetailField label={t('audit.fields.entityType')}>
                  {t(`entities.${log.entityType}`, { defaultValue: log.entityType })}
                </DetailField>
                <DetailField label={t('audit.fields.entityId')}>
                  <code className="text-xs">{log.entityId}</code>
                </DetailField>
                <DetailField label={t('audit.fields.userId')}>
                  {log.userId ? <code className="text-xs">{log.userId}</code> : null}
                </DetailField>
                <DetailField label={t('audit.fields.ipAddress')}>{log.ipAddress}</DetailField>
              </dl>

              <div className="flex flex-col gap-3">
                <h3 className="text-sm font-semibold">{t('audit.changes')}</h3>

                {changes.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t('audit.noChanges')}</p>
                ) : (
                  <ul className="flex flex-col divide-y rounded-lg border">
                    {changes.map((change) => (
                      <li key={change.key} className="flex flex-col gap-1 p-3">
                        <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                          {change.key}
                        </span>
                        <span className="text-sm text-destructive line-through">
                          {formatAuditValue(change.before)}
                        </span>
                        <span className="text-sm">{formatAuditValue(change.after)}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </>
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
