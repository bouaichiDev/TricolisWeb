import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ReferentialDialog } from '../components/ReferentialDialog'
import {
  useCreateReferential,
  useDeleteReferential,
  useReferentialList,
  useUpdateReferential,
} from '../hooks/useReferentials'
import type { PackageReferential, ReferentialFilters, ReferentialKind } from '../types/referential'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

interface ReferentialListPageProps {
  kind: ReferentialKind
  /** Préfixe i18n : `packageTypes` ou `groupingTypes`. */
  namespace: string
}

/**
 * Liste d'un référentiel de colis.
 *
 * Une seule page pour les deux référentiels : ils partagent le contrat
 * (`code`, `name`, `status`), les permissions (`packages.*`) et les filtres.
 * Seuls le chemin et les libellés diffèrent.
 */
export function ReferentialListPage({ kind, namespace }: ReferentialListPageProps) {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<ReferentialFilters>({ page: 1, perPage: 25 })
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<PackageReferential | null>(null)
  const [deleting, setDeleting] = useState<PackageReferential | null>(null)

  const { data, isPending, error, refetch } = useReferentialList(kind, filters)
  const create = useCreateReferential(kind)
  const update = useUpdateReferential(kind)
  const remove = useDeleteReferential(kind)

  const columns: Column<PackageReferential>[] = [
    { key: 'code', header: t('packages.fields.code'), cell: (row) => row.code },
    { key: 'name', header: t('packages.fields.name'), cell: (row) => row.name },
    {
      key: 'status',
      header: t('packages.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <span className="flex justify-end gap-1">
          <PermissionGuard permission="packages.update">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.edit')}
              onClick={() => setEditing(row)}
            >
              <Pencil className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
          <PermissionGuard permission="packages.delete">
            <Button
              variant="ghost"
              size="icon"
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
        title={t(`${namespace}.title`)}
        description={t(`${namespace}.subtitle`)}
        actions={
          <PermissionGuard permission="packages.create">
            <Button onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t(`${namespace}.create`)}
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
        emptyMessage={t(`${namespace}.empty`)}
      />

      <ReferentialDialog
        open={creating}
        onOpenChange={setCreating}
        title={t(`${namespace}.create`)}
        description={t(`${namespace}.subtitle`)}
        onSubmit={(values) => create.mutateAsync(values)}
      />

      {editing ? (
        <ReferentialDialog
          key={editing.id}
          open
          onOpenChange={(open) => !open && setEditing(null)}
          title={t(`${namespace}.edit`)}
          description={t(`${namespace}.subtitle`)}
          defaultValues={{ code: editing.code, name: editing.name, status: editing.status }}
          onSubmit={(values) =>
            update.mutateAsync({ id: editing.id, ...values }).then(() => setEditing(null))
          }
        />
      ) : null}

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: deleting?.name ?? '' })}
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
