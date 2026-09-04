import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { useExportJobs } from '@/modules/exports/hooks/useExports'
import type { ExportJob } from '@/modules/exports/types/export'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDateTime } from '@/shared/utils/format'

/** Alias `MorphMap` de l'entité, et donc `statuses.source`. */
const EXPORT_JOB_SOURCE = 'export_job'

/**
 * L'historique des envois de ce client.
 *
 * C'est **le seul historique** que le modèle contient : il n'existe ni trace
 * d'import, ni journal d'appels API, et le §68 précise que « Historique » veut
 * dire `ExportJob` et rien d'autre.
 *
 * Les envois de facture déclenchés automatiquement par la clôture apparaissent
 * ici : ce sont les mêmes `ExportJob` que la Facturation affiche, lus par le
 * même cache (§48, §77).
 */
export function CustomerExportJobsPanel({ customerId }: { customerId: string }) {
  const { t } = useTranslation()

  const { data, isPending, error, refetch } = useExportJobs({
    page: 1,
    perPage: 25,
    customerId,
  })

  const columns: Column<ExportJob>[] = [
    {
      key: 'fileName',
      header: t('exports.jobs.fields.file'),
      cell: (row) => (
        <Link to={`/integrations/export-jobs/${row.id}`} className="flex flex-col hover:underline">
          <span className="font-medium">{row.fileName ?? t('exports.jobs.untitled')}</span>
          <span className="text-xs text-muted-foreground">{row.configuration?.name ?? ''}</span>
        </Link>
      ),
    },
    {
      key: 'status',
      header: t('exports.jobs.fields.status'),
      cell: (row) => <StatusBadge status={row.status} source={EXPORT_JOB_SOURCE} />,
    },
    {
      key: 'attemptCount',
      header: t('exports.jobs.fields.attempts'),
      hideOnMobile: true,
      className: 'text-right',
      cell: (row) => <span className="tabular-nums">{row.attemptCount}</span>,
    },
    {
      key: 'generatedAt',
      header: t('exports.jobs.fields.generatedAt'),
      hideOnMobile: true,
      cell: (row) => formatDateTime(row.generatedAt),
    },
    {
      key: 'sentAt',
      header: t('exports.jobs.fields.sentAt'),
      cell: (row) =>
        row.sentAt === null ? (
          <span className="text-muted-foreground">{t('exports.jobs.notSent')}</span>
        ) : (
          formatDateTime(row.sentAt)
        ),
    },
  ]

  return (
    <DataTable
      columns={columns}
      rows={data?.data ?? []}
      rowKey={(row) => row.id}
      isLoading={isPending}
      error={error}
      onRetry={() => void refetch()}
      emptyMessage={t('exports.jobs.emptyForCustomer')}
    />
  )
}
