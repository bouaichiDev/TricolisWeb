import { RotateCcw } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'

import { errorMessage } from '@/shared/api/errors'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDateTime } from '@/shared/utils/format'

import { integrationsApi, type ExportJobFilters } from '../api/integrationsApi'
import { useExportJobs, useIntegrationMutation } from '../hooks/useIntegrations'
import type { ExportJob, ExportJobStatus } from '../types'
import { jobStatusLabels } from '../utils/labels'

const STATUS_COLORS: Record<ExportJobStatus, string> = { pending: '#64748b', processing: '#2563eb', sent: '#16a34a', failed: '#dc2626' }

const columns: Column<ExportJob>[] = [
  { key: 'flow', header: 'Flux', cell: (job) => job.configuration?.name ?? '—' },
  { key: 'entity', header: 'Objet', cell: (job) => job.entityReference ?? '—' },
  {
    key: 'status',
    header: 'Statut',
    cell: (job) => <StatusBadge code={job.status} label={jobStatusLabels[job.status] ?? job.status} color={STATUS_COLORS[job.status] ?? null} />,
  },
  { key: 'attempts', header: 'Tentatives', className: 'text-right', cell: (job) => <span className="tabular-nums">{job.attemptCount}</span> },
  { key: 'sentAt', header: 'Transmis le', hideOnMobile: true, cell: (job) => formatDateTime(job.sentAt) },
  { key: 'error', header: 'Motif', hideOnMobile: true, cell: (job) => job.errorMessage ?? '—' },
]

/**
 * L'historique des envois vers les systèmes du client — lecture seule, et
 * relance d'un envoi en échec si le rôle le permet. Un envoi transmis ne se
 * relance pas : le client l'a déjà reçu.
 */
export function ExportJobsSection() {
  const [filters, setFilters] = useState<ExportJobFilters>({ page: 1 })
  const jobs = useExportJobs(filters)
  const retry = useIntegrationMutation(integrationsApi.retryExportJob)

  const relaunch = async (job: ExportJob) => {
    try {
      const updated = await retry.mutateAsync(job.id)
      toast.success(updated.status === 'sent' ? 'Envoi transmis.' : 'Envoi relancé.')
    } catch (caught) {
      toast.error(errorMessage(caught))
    }
  }

  return (
    <SectionCard
      title="Envois"
      description="Chaque transmission vers vos systèmes, avec son résultat."
      actions={
        <select
          aria-label="Filtrer par statut"
          className="h-9 rounded-md border bg-background px-2 text-sm"
          value={filters.status ?? ''}
          onChange={(event) => setFilters({ page: 1, status: (event.target.value || undefined) as ExportJobStatus | undefined })}
        >
          <option value="">Tous les statuts</option>
          {Object.entries(jobStatusLabels).map(([code, label]) => (
            <option key={code} value={code}>{label}</option>
          ))}
        </select>
      }
    >
      <DataTable
        columns={columns}
        rows={jobs.data?.data ?? []}
        rowKey={(job) => job.id}
        meta={jobs.data?.meta}
        isLoading={jobs.isPending}
        errorMessage={jobs.isError ? errorMessage(jobs.error) : null}
        onRetry={() => void jobs.refetch()}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onPerPageChange={(perPage) => setFilters((current) => ({ ...current, perPage, page: 1 }))}
        emptyMessage="Aucun envoi."
        actions={(job) =>
          job.actions.canRetry && (
            <RowActions actions={[{ key: 'retry', icon: RotateCcw, label: 'Relancer', disabled: retry.isPending, onClick: () => void relaunch(job) }]} />
          )
        }
      />
    </SectionCard>
  )
}
