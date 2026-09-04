import { Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import type { Depot } from '../types/depot'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Button } from '@/shared/components/ui/button'
import type { PaginationMeta } from '@/shared/api/types'

interface DepotTableProps {
  agencyId: string
  rows: Depot[]
  meta?: PaginationMeta
  isLoading: boolean
  error: Error | null
  onPageChange: (page: number) => void
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
    {
      key: 'actions',
      header: '',
      className: 'w-12',
      cell: (row) => (
        <PermissionGuard permission="depots.delete">
          <Button
            variant="ghost"
            size="icon"
            aria-label={t('common.delete')}
            onClick={() => onDelete(row)}
          >
            <Trash2 className="size-4" aria-hidden />
          </Button>
        </PermissionGuard>
      ),
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
      onRetry={onRetry}
      emptyMessage={t('depots.empty')}
    />
  )
}
