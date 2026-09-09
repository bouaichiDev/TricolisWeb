import { Pencil, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { StatusBadge } from '@/shared/components/data/StatusBadge'

import type { TypeItem } from '../types/type'
import type { PaginationMeta } from '@/shared/api/types'

interface TypeItemTableProps {
  items: TypeItem[]
  meta?: PaginationMeta
  isLoading: boolean
  error: Error | null
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
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
  onPerPageChange,
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
      onPerPageChange={onPerPageChange}
      actions={(row) => (
        <RowActions
          actions={[
            {
              key: 'edit',
              icon: Pencil,
              // Le nom est repris dans le libellé : dans une table de dix lignes,
              // « Modifier » seul ne dit pas quoi, et un lecteur d'écran entend dix
              // fois la même chose.
              label: `${t('common.edit')} ${row.name}`,
              permission: 'types.update',
              onClick: () => onEdit(row),
            },
            {
              key: 'delete',
              icon: Trash2,
              label: `${t('common.delete')} ${row.name}`,
              tone: 'danger',
              permission: 'types.delete',
              onClick: () => onDelete(row),
            },
          ]}
        />
      )}
      onRetry={onRetry}
      emptyMessage={t('types.noItem')}
    />
  )
}
