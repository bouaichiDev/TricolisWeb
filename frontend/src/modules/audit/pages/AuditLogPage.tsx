import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { AuditDetailSheet } from '../components/AuditDetailSheet'
import { AuditFilterBar } from '../components/AuditFilterBar'
import { useAuditLogs } from '../hooks/useAuditLogs'
import type { AuditFilters, AuditLog } from '../types/auditLog'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'
import { formatDateTime } from '@/shared/utils/format'

const INITIAL_FILTERS: AuditFilters = { page: 1, perPage: 25 }

/**
 * Journal d'audit.
 *
 * Consultation seule, sans action : l'API n'expose aucune écriture, et une
 * ligne modifiable ne serait plus une trace. Le tri n'est pas proposé non plus
 * — le backend impose l'ordre antéchronologique.
 */
export function AuditLogPage() {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<AuditFilters>(INITIAL_FILTERS)
  const [selected, setSelected] = useState<AuditLog | null>(null)

  const { data, isPending, error, refetch } = useAuditLogs(filters)

  const columns: Column<AuditLog>[] = [
    {
      key: 'createdAt',
      header: t('audit.fields.createdAt'),
      cell: (row) => formatDateTime(row.createdAt),
    },
    {
      key: 'action',
      header: t('audit.fields.action'),
      cell: (row) => (
        <Badge variant="secondary" className="font-normal">
          {t(`auditActions.${row.action}`, { defaultValue: row.action })}
        </Badge>
      ),
    },
    {
      key: 'entityType',
      header: t('audit.fields.entityType'),
      cell: (row) => t(`entities.${row.entityType}`, { defaultValue: row.entityType }),
    },
    {
      key: 'entityId',
      header: t('audit.fields.entityId'),
      hideOnMobile: true,
      cell: (row) => <code className="text-xs text-muted-foreground">{row.entityId}</code>,
    },
    {
      key: 'ipAddress',
      header: t('audit.fields.ipAddress'),
      hideOnMobile: true,
      cell: (row) => row.ipAddress ?? <span className="text-muted-foreground">—</span>,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('audit.title')} description={t('audit.subtitle')} />

      <AuditFilterBar
        filters={filters}
        onChange={(patch) => setFilters((current) => ({ ...current, ...patch, page: 1 }))}
        onReset={() => setFilters(INITIAL_FILTERS)}
      />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        onRowClick={setSelected}
        emptyMessage={t('audit.empty')}
      />

      <AuditDetailSheet log={selected} onClose={() => setSelected(null)} />
    </div>
  )
}
