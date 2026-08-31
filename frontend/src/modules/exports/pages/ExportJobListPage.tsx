import { Plus, RotateCw } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { GenerateExportDialog } from '../components/GenerateExportDialog'
import { useExportJobs, useRetryExportJob } from '../hooks/useExports'
import { isRetryable, type ExportJob } from '../types/export'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { useCustomerList } from '@/modules/customers/hooks/useCustomers'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

/** Sentinelle « tous les clients » : Radix refuse une option de valeur vide. */
const ALL_CUSTOMERS = 'all'

/**
 * L'historique des envois.
 *
 * **Ce que l'exploitant vient y chercher, c'est un échec.** Un envoi manqué
 * n'annule pas la clôture (§27) : il s'écrit ici, avec son message et son
 * nombre de tentatives, et se reprend depuis ce même écran.
 *
 * Un envoi déjà transmis ne se rejoue pas : le client aurait deux fois la même
 * facture. Le bouton disparaît alors plutôt que d'échouer en 409 — mais c'est
 * bien le serveur qui refuse, sous verrou.
 *
 * **Aucun téléchargement.** La route `download` et la permission qui
 * l'accompagnerait n'existent pas : le §58 interdit par ailleurs de fabriquer
 * une URL depuis `storagePath`, que le serveur ne renvoie pas. La colonne dit
 * qu'un fichier existe, sans promettre de le servir.
 */
export function ExportJobListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [generating, setGenerating] = useState(false)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<string | undefined>(undefined)
  const [customerId, setCustomerId] = useState(ALL_CUSTOMERS)
  const [page, setPage] = useState(1)

  const customers = useCustomerList({ page: 1, perPage: 100 })
  const retry = useRetryExportJob()

  const { data, isPending, error, refetch } = useExportJobs({
    page,
    search: search || undefined,
    status,
    customerId: customerId === ALL_CUSTOMERS ? undefined : customerId,
  })

  const columns: Column<ExportJob>[] = [
    {
      key: 'fileName',
      header: t('exports.jobs.fields.file'),
      cell: (row) => (
        <span className="flex flex-col">
          <span className="font-medium">{row.fileName ?? '—'}</span>
          <span className="text-xs text-muted-foreground">{row.configuration?.name ?? ''}</span>
        </span>
      ),
    },
    {
      key: 'entity',
      header: t('exports.jobs.fields.entityType'),
      hideOnMobile: true,
      cell: (row) =>
        row.entityType === null ? (
          <span className="text-muted-foreground">—</span>
        ) : (
          t(`entities.${row.entityType}`, { defaultValue: row.entityType })
        ),
    },
    {
      key: 'status',
      header: t('exports.jobs.fields.status'),
      // La source du référentiel est `export_job`, au singulier : c'est l'alias
      // de `MorphMap`, et c'est lui que `statuses.source` porte.
      cell: (row) => <StatusBadge status={row.status} source="export_job" />,
    },
    {
      key: 'attemptCount',
      header: t('exports.jobs.fields.attempts'),
      className: 'text-right',
      cell: (row) => <span className="tabular-nums">{row.attemptCount}</span>,
    },
    {
      key: 'generatedAt',
      header: t('exports.jobs.fields.generatedAt'),
      cell: (row) => formatDateTime(row.generatedAt),
    },
    {
      key: 'sentAt',
      header: t('exports.jobs.fields.sentAt'),
      cell: (row) => formatDateTime(row.sentAt),
    },
    {
      key: 'errorMessage',
      header: t('exports.jobs.fields.error'),
      cell: (row) =>
        row.errorMessage ? (
          <span className="text-sm text-destructive">{row.errorMessage}</span>
        ) : null,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-12',
      cell: (row) =>
        isRetryable(row) ? (
          <PermissionGuard permission="export_jobs.retry">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('exports.jobs.retry')}
              disabled={retry.isPending}
              onClick={(event) => {
                // Sans cela, le clic ouvrirait aussi la fiche de l'envoi.
                event.stopPropagation()
                retry.mutate(row.id)
              }}
            >
              <RotateCw className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
        ) : null,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('exports.jobs.title')}
        description={t('exports.jobs.subtitle')}
        actions={
          <PermissionGuard permission="export_jobs.create">
            <Button onClick={() => setGenerating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('exports.jobs.generate')}
            </Button>
          </PermissionGuard>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div className="w-full sm:w-56">
          <AsyncSelect
            label={t('exports.jobs.fields.customer')}
            value={customerId}
            onChange={(value) => {
              setCustomerId(value)
              setPage(1)
            }}
            options={[
              { value: ALL_CUSTOMERS, label: t('exports.jobs.allCustomers') },
              ...(customers.data?.data ?? []).map((customer) => ({
                value: customer.id,
                label: customer.name,
                hint: customer.code,
              })),
            ]}
            isLoading={customers.isPending}
          />
        </div>

        <SearchInput
          value={search}
          onChange={(value) => {
            setSearch(value)
            setPage(1)
          }}
        />

        <StatusFilterSelect
          source="export_job"
          value={status}
          onChange={(value) => {
            setStatus(value)
            setPage(1)
          }}
        />
      </div>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={setPage}
        onRetry={() => void refetch()}
        onRowClick={(row) => void navigate(`/integrations/export-jobs/${row.id}`)}
        emptyMessage={t('exports.jobs.empty')}
      />

      <GenerateExportDialog open={generating} onOpenChange={setGenerating} />
    </div>
  )
}
