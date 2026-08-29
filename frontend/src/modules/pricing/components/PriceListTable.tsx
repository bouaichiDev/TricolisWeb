import { Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import type { PriceList } from '../types/pricing'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import type { PaginationMeta } from '@/shared/api/types'
import { formatDate } from '@/shared/utils/format'

interface PriceListTableProps {
  rows: PriceList[]
  meta?: PaginationMeta
  isLoading: boolean
  error: Error | null
  onPageChange: (page: number) => void
  onRetry: () => void
  onDelete: (list: PriceList) => void
  /** La colonne clients n'a de sens que sur les barèmes négociés. */
  showCustomers?: boolean
}

/** Les barèmes, avec ce qu'ils portent et pour qui ils valent. */
export function PriceListTable({
  rows,
  meta,
  isLoading,
  error,
  onPageChange,
  onRetry,
  onDelete,
  showCustomers = false,
}: PriceListTableProps) {
  const { t } = useTranslation()

  const columns: Column<PriceList>[] = [
    {
      key: 'code',
      header: t('pricing.lists.fields.code'),
      cell: (row) => (
        <Link
          to={`/billing/pricing/${row.id}`}
          className="flex flex-col text-primary hover:underline"
        >
          <span className="font-medium">{row.code}</span>
          <span className="text-xs text-muted-foreground">{row.name}</span>
        </Link>
      ),
    },
    ...(showCustomers
      ? [
          {
            key: 'customers',
            header: t('pricing.lists.fields.customers'),
            cell: (row: PriceList) => (
              <span className="flex flex-wrap gap-1">
                {(row.customers ?? []).map((customer) => (
                  <Badge key={customer.id} variant="secondary">
                    {customer.name}
                  </Badge>
                ))}
              </span>
            ),
          },
        ]
      : []),
    {
      key: 'content',
      header: t('pricing.lists.fields.content'),
      cell: (row) => (
        <span className="text-sm">
          {t('pricing.lists.counts', {
            rules: row.ruleCount ?? 0,
            matrices: row.matrixCount ?? 0,
          })}
        </span>
      ),
    },
    {
      key: 'validity',
      header: t('pricing.lists.fields.validity'),
      cell: (row) =>
        row.validFrom || row.validTo
          ? `${formatDate(row.validFrom)} — ${formatDate(row.validTo)}`
          : t('pricing.lists.always'),
    },
    {
      key: 'isActive',
      header: t('pricing.lists.fields.isActive'),
      cell: (row) => (
        <Badge variant={row.isActive ? 'default' : 'secondary'}>
          {row.isActive ? t('common.yes') : t('common.no')}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: '',
      className: 'w-12',
      cell: (row) => (
        <PermissionGuard permission="price_lists.delete">
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
      emptyMessage={t('pricing.lists.empty')}
    />
  )
}
