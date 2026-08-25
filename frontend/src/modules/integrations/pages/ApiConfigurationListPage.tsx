import { KeyRound, Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { ApiConfigurationDialog } from '../components/ApiConfigurationDialog'
import {
  useApiConfigurationList,
  useDeleteApiConfiguration,
} from '../hooks/useApiConfigurations'
import type { ApiConfiguration, ApiConfigurationFilters } from '../types/apiConfiguration'

/**
 * Les API externes que l'organisme appelle.
 *
 * Sens inverse des accès API client, où un client détient une clé pour nous
 * appeler. Ici c'est nous qui appelons — la position d'un chauffeur, par
 * exemple, rendue par la télématique de l'organisme.
 *
 * **Le secret n'est jamais affiché.** Une pastille dit s'il est posé ; il ne
 * peut être que remplacé. Le relire n'apporterait rien et le ferait circuler.
 */
export function ApiConfigurationListPage() {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<ApiConfigurationFilters>({ page: 1, perPage: 25 })
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<ApiConfiguration | null>(null)
  const [deleting, setDeleting] = useState<ApiConfiguration | null>(null)

  const { data, isPending, error, refetch } = useApiConfigurationList(filters)
  const remove = useDeleteApiConfiguration()

  const columns: Column<ApiConfiguration>[] = [
    {
      key: 'name',
      header: t('apiConfigurations.fields.name'),
      cell: (row) => <span className="font-medium">{row.name}</span>,
    },
    {
      key: 'code',
      header: t('apiConfigurations.fields.code'),
      cell: (row) => <span className="font-mono text-sm">{row.code}</span>,
    },
    {
      key: 'baseUrl',
      header: t('apiConfigurations.fields.baseUrl'),
      hideOnMobile: true,
      cell: (row) => <span className="truncate font-mono text-xs">{row.baseUrl}</span>,
    },
    {
      key: 'authType',
      header: t('apiConfigurations.fields.authType'),
      hideOnMobile: true,
      cell: (row) => (
        <span className="flex items-center gap-1.5">
          {t(`authTypes.${row.authType}`)}
          {row.hasCredentials ? (
            <KeyRound className="size-3.5 text-muted-foreground" aria-label={t('apiConfigurations.secretSet')} />
          ) : null}
        </span>
      ),
    },
    {
      key: 'lastUsedAt',
      header: t('apiConfigurations.fields.lastUsedAt'),
      hideOnMobile: true,
      cell: (row) =>
        row.lastUsedAt === null ? (
          <span className="text-muted-foreground">{t('apiConfigurations.neverUsed')}</span>
        ) : (
          formatDateTime(row.lastUsedAt)
        ),
    },
    {
      key: 'isActive',
      header: t('apiConfigurations.fields.isActive'),
      cell: (row) => <StatusBadge status={row.isActive ? 'active' : 'inactive'} />,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <span className="flex justify-end gap-1">
          <PermissionGuard permission="api_configurations.update">
            <Button
              variant="ghost"
              size="icon"
              title={t('common.edit')}
              aria-label={t('common.edit')}
              onClick={() => setEditing(row)}
            >
              <Pencil className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>

          <PermissionGuard permission="api_configurations.delete">
            <Button
              variant="ghost"
              size="icon"
              title={t('common.delete')}
              aria-label={t('common.delete')}
              onClick={() => setDeleting(row)}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
        </span>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('apiConfigurations.title')}
        description={t('apiConfigurations.description')}
        actions={
          <PermissionGuard permission="api_configurations.create">
            <Button size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('apiConfigurations.create')}
            </Button>
          </PermissionGuard>
        }
      />

      <SearchInput
        value={filters.search ?? ''}
        onChange={(search) =>
          setFilters((current) => ({ ...current, page: 1, search: search || undefined }))
        }
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
        emptyMessage={t('apiConfigurations.empty')}
      />

      {creating || editing !== null ? (
        <ApiConfigurationDialog
          key={editing?.id ?? 'new'}
          configuration={editing}
          open
          onOpenChange={(open) => {
            if (open) return
            setCreating(false)
            setEditing(null)
          }}
        />
      ) : null}

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('confirm.deleteTitle')}
        description={t('apiConfigurations.deleteConfirm', { name: deleting?.name ?? '' })}
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
