import { Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { useDeleteSettlement, useSettlementList } from '../hooks/useSettlements'
import type { ProviderSettlement } from '../types/settlement'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import { formatDate, formatMoney } from '@/shared/utils/format'

/**
 * Liste des décomptes fournisseurs.
 *
 * La suppression ne s'affiche que sur un brouillon : un décompte clôturé est
 * arrêté avec le fournisseur, et le retirer laisserait un accord sans trace.
 */
export function SettlementListPage() {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<string | undefined>(undefined)
  const [page, setPage] = useState(1)
  const [toDelete, setToDelete] = useState<ProviderSettlement | null>(null)

  const { data, isPending, error, refetch } = useSettlementList({
    page,
    search: search || undefined,
    status,
  })
  const remove = useDeleteSettlement()

  const columns: Column<ProviderSettlement>[] = [
    {
      key: 'settlementNumber',
      header: t('settlements.fields.settlementNumber'),
      cell: (row) => (
        <Link
          to={`/billing/settlements/${row.id}`}
          className="font-medium text-primary hover:underline"
        >
          {row.settlementNumber}
        </Link>
      ),
    },
    {
      key: 'providerName',
      header: t('settlements.fields.provider'),
      cell: (row) => row.providerName ?? '',
    },
    {
      key: 'period',
      header: t('settlements.fields.period'),
      cell: (row) =>
        row.periodFrom || row.periodTo
          ? `${formatDate(row.periodFrom)} — ${formatDate(row.periodTo)}`
          : '',
    },
    {
      key: 'total',
      header: t('settlements.fields.total'),
      className: 'text-right',
      cell: (row) => <span className="tabular-nums">{formatMoney(row.total)}</span>,
    },
    {
      key: 'status',
      header: t('settlements.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-12',
      cell: (row) =>
        row.status === 'draft' ? (
          <PermissionGuard permission="provider_settlements.delete">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.delete')}
              onClick={() => setToDelete(row)}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
        ) : null,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('settlements.title')}
        description={t('settlements.subtitle')}
        actions={
          <PermissionGuard permission="provider_settlements.create">
            <Button asChild>
              <Link to="/billing/settlements/create">
                <Plus className="size-4" aria-hidden />
                {t('settlements.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <SearchInput
          value={search}
          onChange={(value) => {
            setSearch(value)
            setPage(1)
          }}
        />
        <StatusFilterSelect
          source="provider_settlement"
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
        emptyMessage={t('settlements.empty')}
      />

      <ConfirmDialog
        open={toDelete !== null}
        onOpenChange={(open) => !open && setToDelete(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: toDelete?.settlementNumber ?? '' })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (toDelete === null) return
          remove.mutate(toDelete.id, { onSuccess: () => setToDelete(null) })
        }}
      />
    </div>
  )
}
