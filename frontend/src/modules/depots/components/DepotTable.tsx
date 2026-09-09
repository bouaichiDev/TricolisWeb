import { Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import type { Depot } from '../types/depot'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import type { PaginationMeta } from '@/shared/api/types'

interface DepotTableProps {
  agencyId: string
  rows: Depot[]
  meta?: PaginationMeta
  isLoading: boolean
  error: Error | null
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
  onRetry: () => void
  onDelete: (depot: Depot) => void
}

/** Table des depots, partagee par la page dediee et l'onglet de la fiche agence. */
export function DepotTable({
  agencyId,
  rows,
  meta,
  isLoading,
  error,
  onPageChange,
  onPerPageChange,
  onRetry,
  onDelete,
}: DepotTableProps) {
  const { t } = useTranslation()

  const columns: Column<Depot>[] = [
    {
      key: 'code',
      header: t('depots.fields.code'),
      cell: (row) => (
        <Link
          to={`/agencies/${agencyId}/depots/${row.id}`}
          className="font-medium text-primary hover:underline"
        >
          {row.code}
        </Link>
      ),
    },
    { key: 'name', header: t('depots.fields.name'), cell: (row) => row.name },
    {
      key: 'status',
      header: t('depots.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
  ]

  return (
    <DataTable
      columns={columns}
      rows={rows}
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
              key: 'delete',
              icon: Trash2,
              label: t('common.delete'),
              tone: 'danger',
              permission: 'depots.delete',
              onClick: () => onDelete(row),
            },
          ]}
        />
      )}
      onRetry={onRetry}
      emptyMessage={t('depots.empty')}
    />
  )
}
