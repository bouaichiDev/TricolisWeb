import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { StatusDialog } from '../components/StatusDialog'
import { useDeleteStatus, useStatusList, useStatusSources } from '../hooks/useStatuses'
import { statusColumns } from './statusColumns'
import { STATUS_SORTABLE, type Status, type StatusFilters } from '../types/status'

const INITIAL: StatusFilters = { page: 1, perPage: 25, sort: 'source', direction: 'asc' }

/** Valeur désignant « toutes les entités » ; Radix refuse une option vide. */
const ALL_SOURCES = 'all'

/**
 * Référentiel des statuts, écran de la plateforme.
 *
 * Il donne à un code brut son libellé, son icône et son rang, pour toutes les
 * entités qui portent un statut. Tout membre le consulte ; seule la plateforme
 * l'écrit, et les boutons suivent les permissions.
 */
export function StatusListPage() {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<StatusFilters>(INITIAL)
  const [editing, setEditing] = useState<Status | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<Status | null>(null)

  const { data, isPending, error, refetch } = useStatusList(filters)
  const sources = useStatusSources()
  const remove = useDeleteStatus()

  const columns = statusColumns(t, { onEdit: setEditing, onDelete: setDeleting })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('statuses.title')}
        description={t('statuses.subtitle')}
        actions={
          <PermissionGuard permission="statuses.create">
            <Button onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('statuses.create')}
            </Button>
          </PermissionGuard>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(search) =>
            setFilters((current) => ({ ...current, page: 1, search: search || undefined }))
          }
          placeholder={t('statuses.searchPlaceholder')}
        />

        <div className="w-full sm:max-w-xs">
          <AsyncSelect
            label={t('statuses.fields.source')}
            value={filters.source ?? ALL_SOURCES}
            onChange={(source) =>
              setFilters((current) => ({
                ...current,
                page: 1,
                source: source === ALL_SOURCES ? undefined : source,
              }))
            }
            options={[
              { value: ALL_SOURCES, label: t('common.all') },
              ...(sources.data ?? []).map((source) => ({
                value: source,
                label: t(`entities.${source}`, { defaultValue: source }),
                hint: source,
              })),
            ]}
            isLoading={sources.isPending}
          />
        </div>
      </div>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        sort={filters.sort}
        direction={filters.direction}
        onSortChange={(sortKey) => {
          if (!STATUS_SORTABLE.includes(sortKey as (typeof STATUS_SORTABLE)[number])) return

          setFilters((current) => ({
            ...current,
            sort: sortKey,
            direction: current.sort === sortKey && current.direction === 'asc' ? 'desc' : 'asc',
          }))
        }}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        emptyMessage={t('statuses.empty')}
      />

      <StatusDialog
        key={editing?.id ?? 'new'}
        status={editing}
        open={editing !== null || creating}
        onOpenChange={(open) => {
          if (!open) {
            setEditing(null)
            setCreating(false)
          }
        }}
      />

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('common.delete')}
        description={t('statuses.deleteConfirm')}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (deleting === null) return
          remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
        }}
      />
    </div>
  )
}
