import { Eye } from 'lucide-react'
import { useState } from 'react'

import { errorMessage } from '@/shared/api/errors'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDateTime } from '@/shared/utils/format'

import { useImportRuns } from '../hooks/useImportRuns'
import { importRunChannelLabels, importRunStatusLabels, runSummary, type ImportRun, type ImportRunFilters, type ImportRunStatus } from '../importRunTypes'
import { ImportRunDialog } from './ImportRunDialog'

const STATUS_COLORS: Record<ImportRunStatus, string> = { completed: '#16a34a', completed_with_errors: '#d97706', rejected: '#dc2626', failed: '#dc2626' }

const columns: Column<ImportRun>[] = [
  { key: 'date', header: 'Date', cell: (run) => formatDateTime(run.finishedAt) },
  { key: 'flow', header: 'Flux', cell: (run) => run.configuration?.name ?? '—' },
  { key: 'channel', header: 'Origine', hideOnMobile: true, cell: (run) => importRunChannelLabels[run.channel] ?? run.channel },
  { key: 'file', header: 'Fichier', hideOnMobile: true, cell: (run) => run.fileName ?? '—' },
  {
    key: 'status',
    header: 'Statut',
    cell: (run) => <StatusBadge code={run.status} label={importRunStatusLabels[run.status] ?? run.status} color={STATUS_COLORS[run.status] ?? null} />,
  },
  { key: 'summary', header: 'Bilan', hideOnMobile: true, cell: runSummary },
]

/**
 * L'historique des imports : dépôts dans le portail, relèves automatiques,
 * imports faits par le transporteur. Le détail des commandes refusées
 * s'ouvre sur la fiche.
 */
export function ImportRunsSection() {
  const [filters, setFilters] = useState<ImportRunFilters>({ page: 1 })
  const [openId, setOpenId] = useState<string | null>(null)
  const runs = useImportRuns(filters)

  return (
    <SectionCard
      title="Historique des imports"
      description="Chaque import de commandes, son bilan et les lignes refusées."
      actions={
        <select
          aria-label="Filtrer l’historique par statut"
          className="h-9 rounded-md border bg-background px-2 text-sm"
          value={filters.status ?? ''}
          onChange={(event) => setFilters({ page: 1, status: (event.target.value || undefined) as ImportRunStatus | undefined })}
        >
          <option value="">Tous les statuts</option>
          {Object.entries(importRunStatusLabels).map(([code, label]) => (
            <option key={code} value={code}>{label}</option>
          ))}
        </select>
      }
    >
      <DataTable
        columns={columns}
        rows={runs.data?.data ?? []}
        rowKey={(run) => run.id}
        meta={runs.data?.meta}
        isLoading={runs.isPending}
        errorMessage={runs.isError ? errorMessage(runs.error) : null}
        onRetry={() => void runs.refetch()}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onPerPageChange={(perPage) => setFilters((current) => ({ ...current, perPage, page: 1 }))}
        emptyMessage="Aucun import pour l’instant."
        actions={(run) => <RowActions actions={[{ key: 'detail', icon: Eye, label: 'Détail', onClick: () => setOpenId(run.id) }]} />}
      />
      <ImportRunDialog runId={openId} onClose={() => setOpenId(null)} />
    </SectionCard>
  )
}
