import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { CatalogItemDialog } from './CatalogItemDialog'
import {
  useCatalogItemList,
  useCreateCatalogItem,
  useDeleteCatalogItem,
  useUpdateCatalogItem,
} from '../hooks/useCatalogItems'
import {
  toCatalogItemFormValues,
  toCatalogItemPayload,
} from '../schemas/catalogSchema'
import type { CatalogItem, CatalogItemFilters } from '../types/catalog'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { Button } from '@/shared/components/ui/button'

/** Articles d'un catalogue, paginés côté serveur. */
export function CatalogItemTable({
  customerId,
  catalogId,
}: {
  customerId: string
  catalogId: string
}) {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<CatalogItemFilters>({ page: 1, perPage: 25 })
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<CatalogItem | null>(null)
  const [deleting, setDeleting] = useState<CatalogItem | null>(null)

  const { data, isPending, error, refetch } = useCatalogItemList(customerId, catalogId, filters)
  const create = useCreateCatalogItem(customerId, catalogId)
  const update = useUpdateCatalogItem(customerId, catalogId)
  const remove = useDeleteCatalogItem(customerId, catalogId)

  const number = (value: number | string | null) =>
    value === null ? <span className="text-muted-foreground">—</span> : String(value)

  const columns: Column<CatalogItem>[] = [
    {
      key: 'articleCode',
      header: t('catalogItems.fields.articleCode'),
      cell: (row) => <span className="font-medium">{row.articleCode}</span>,
    },
    { key: 'name', header: t('catalogItems.fields.name'), cell: (row) => row.name },
    {
      key: 'barcode',
      header: t('catalogItems.fields.barcode'),
      hideOnMobile: true,
      cell: (row) => row.barcode ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'weight',
      header: t('catalogItems.fields.weight'),
      hideOnMobile: true,
      cell: (row) => number(row.weight),
    },
    {
      key: 'volume',
      header: t('catalogItems.fields.volume'),
      hideOnMobile: true,
      cell: (row) => number(row.volume),
    },
    {
      key: 'status',
      header: t('catalogItems.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <span className="flex justify-end gap-1">
          <PermissionGuard permission="catalogs.update">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.edit')}
              onClick={() => setEditing(row)}
            >
              <Pencil className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
          <PermissionGuard permission="catalogs.delete">
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
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(search) =>
            setFilters((current) => ({ ...current, page: 1, search: search || undefined }))
          }
        />

        <PermissionGuard permission="catalogs.update">
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus className="size-4" aria-hidden />
            {t('catalogItems.create')}
          </Button>
        </PermissionGuard>
      </div>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        emptyMessage={t('catalogItems.empty')}
      />

      <CatalogItemDialog
        open={creating}
        onOpenChange={setCreating}
        onSubmit={(values) => create.mutateAsync(toCatalogItemPayload(values))}
      />

      {editing ? (
        <CatalogItemDialog
          key={editing.id}
          open
          onOpenChange={(open) => !open && setEditing(null)}
          defaultValues={toCatalogItemFormValues(editing)}
          onSubmit={(values) =>
            update
              .mutateAsync({ id: editing.id, ...toCatalogItemPayload(values) })
              .then(() => setEditing(null))
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
