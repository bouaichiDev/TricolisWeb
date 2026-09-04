import { Pencil, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Button } from '@/shared/components/ui/button'

import type { TypeItem } from '../types/type'
import type { PaginationMeta } from '@/shared/api/types'

interface TypeItemTableProps {
  items: TypeItem[]
  meta?: PaginationMeta
  isLoading: boolean
  error: Error | null
  onPageChange: (page: number) => void
  onRetry: () => void
  onEdit: (item: TypeItem) => void
  onDelete: (item: TypeItem) => void
}

/** Les valeurs de la source retenue, à droite. */
export function TypeItemTable({
  items,
  meta,
  isLoading,
  error,
  onPageChange,
  onRetry,
  onEdit,
  onDelete,
}: TypeItemTableProps) {
  const { t } = useTranslation()

  const columns: Column<TypeItem>[] = [
    { key: 'code', header: t('types.fields.code'), cell: (row) => row.code },
    { key: 'name', header: t('types.fields.name'), cell: (row) => row.name },
    {
      key: 'status',
      header: t('types.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <span className="flex justify-end gap-1">
          <PermissionGuard permission="types.update">
            <Button
              variant="ghost"
              size="icon"
              title={t('common.edit')}
              aria-label={`${t('common.edit')} ${row.name}`}
              onClick={() => onEdit(row)}
            >
              <Pencil className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>

          <PermissionGuard permission="types.delete">
            <Button
              variant="ghost"
              size="icon"
              title={t('common.delete')}
              aria-label={`${t('common.delete')} ${row.name}`}
              onClick={() => onDelete(row)}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
        </span>
      ),
    },
  ]

  return (
    <DataTable
      columns={columns}
      rows={items}
      rowKey={(row) => row.id}
      meta={meta}
      isLoading={isLoading}
      error={error}
      onPageChange={onPageChange}
      onRetry={onRetry}
      emptyMessage={t('types.noItem')}
    />
  )
}
