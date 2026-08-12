import { X } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import type { AuditFilters } from '../types/auditLog'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

interface AuditFilterBarProps {
  filters: AuditFilters
  onChange: (patch: Partial<AuditFilters>) => void
  onReset: () => void
}

/**
 * Filtres du journal.
 *
 * Ils reprennent exactement ceux acceptés par `AuditLogController::index` :
 * `userId`, `action`, `entityType`, `entityId`, `createdFrom`, `createdTo`.
 * Aucun autre n'est proposé — un champ que l'API ignore donnerait l'illusion
 * d'un filtrage qui n'a pas lieu.
 */
export function AuditFilterBar({ filters, onChange, onReset }: AuditFilterBarProps) {
  const { t } = useTranslation()

  const hasFilters =
    Boolean(filters.action) ||
    Boolean(filters.entityType) ||
    Boolean(filters.userId) ||
    Boolean(filters.createdFrom) ||
    Boolean(filters.createdTo)

  return (
    <div className="flex flex-col gap-4 rounded-lg border bg-card p-4">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Field label={t('audit.filters.action')}>
          <Input
            value={filters.action ?? ''}
            placeholder="created"
            onChange={(event) => onChange({ action: event.target.value || undefined })}
          />
        </Field>

        <Field label={t('audit.filters.entityType')}>
          <Input
            value={filters.entityType ?? ''}
            placeholder="customer"
            onChange={(event) => onChange({ entityType: event.target.value || undefined })}
          />
        </Field>

        <Field label={t('audit.filters.createdFrom')}>
          <Input
            type="date"
            value={filters.createdFrom ?? ''}
            onChange={(event) => onChange({ createdFrom: event.target.value || undefined })}
          />
        </Field>

        <Field label={t('audit.filters.createdTo')}>
          <Input
            type="date"
            value={filters.createdTo ?? ''}
            onChange={(event) => onChange({ createdTo: event.target.value || undefined })}
          />
        </Field>
      </div>

      {hasFilters ? (
        <div className="flex justify-end">
          <Button variant="ghost" size="sm" onClick={onReset}>
            <X className="size-4" aria-hidden />
            {t('common.reset')}
          </Button>
        </div>
      ) : null}
    </div>
  )
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-2">
      <Label>{label}</Label>
      {children}
    </div>
  )
}
