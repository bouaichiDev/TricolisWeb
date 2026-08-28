import { Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'

import { useRemoveSettlementLine, useSettlement } from '../hooks/useSettlements'
import type { ProviderSettlementLine } from '../types/settlement'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDate, formatMoney } from '@/shared/utils/format'

/**
 * Un décompte fournisseur.
 *
 * Il n'y a **pas d'envoi** : le §108 le dit sans détour — le besoin d'export
 * porte sur les factures clients, et transmettre un décompte par le même
 * mécanisme serait une décision qu'on n'a pas prise. L'écran ne propose donc
 * aucune destination, et n'en invente pas.
 */
export function SettlementDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams<{ id: string }>()
  const [toRemove, setToRemove] = useState<ProviderSettlementLine | null>(null)

  const { data: settlement, isPending, error, refetch } = useSettlement(id)
  const removeLine = useRemoveSettlementLine(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!settlement) return null

  const editable = settlement.status === 'draft'

  const columns: Column<ProviderSettlementLine>[] = [
    {
      key: 'description',
      header: t('settlements.lines.description'),
      cell: (row) => row.description,
    },
    {
      key: 'quantity',
      header: t('settlements.lines.quantity'),
      className: 'text-right',
      cell: (row) => <span className="tabular-nums">{row.quantity}</span>,
    },
    {
      key: 'unitCost',
      header: t('settlements.lines.unitCost'),
      className: 'text-right',
      cell: (row) => <span className="tabular-nums">{formatMoney(row.unitCost)}</span>,
    },
    {
      key: 'totalCost',
      header: t('settlements.lines.totalCost'),
      className: 'text-right',
      cell: (row) => <span className="tabular-nums">{formatMoney(row.totalCost)}</span>,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-12',
      cell: (row) =>
        editable ? (
          <PermissionGuard permission="provider_settlements.update">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.delete')}
              onClick={() => setToRemove(row)}
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
        title={settlement.settlementNumber}
        description={settlement.provider?.name ?? ''}
        actions={<StatusBadge status={settlement.status} />}
      />

      <SectionCard title={t('settlements.sections.header')}>
        <dl className="grid gap-x-8 sm:grid-cols-3">
          <DetailField label={t('settlements.fields.provider')}>
            {settlement.provider?.name ?? ''}
          </DetailField>
          <DetailField label={t('settlements.fields.period')}>
            {settlement.periodFrom || settlement.periodTo
              ? `${formatDate(settlement.periodFrom)} — ${formatDate(settlement.periodTo)}`
              : ''}
          </DetailField>
          <DetailField label={t('settlements.fields.status')}>
            <StatusBadge status={settlement.status} />
          </DetailField>
          <DetailField label={t('settlements.fields.subtotal')}>
            {formatMoney(settlement.subtotal)}
          </DetailField>
          <DetailField label={t('settlements.fields.taxTotal')}>
            {formatMoney(settlement.taxTotal)}
          </DetailField>
          <DetailField label={t('settlements.fields.total')}>
            <span className="font-medium">{formatMoney(settlement.total)}</span>
          </DetailField>
        </dl>
      </SectionCard>

      <SectionCard title={t('settlements.sections.lines')}>
        <DataTable
          columns={columns}
          rows={settlement.lines ?? []}
          rowKey={(row) => row.id}
          emptyMessage={t('settlements.lines.empty')}
        />
      </SectionCard>

      <ConfirmDialog
        open={toRemove !== null}
        onOpenChange={(open) => !open && setToRemove(null)}
        title={t('confirm.deleteTitle')}
        description={t('settlements.lines.confirmRemove', {
          description: toRemove?.description ?? '',
        })}
        confirmLabel={t('common.delete')}
        isPending={removeLine.isPending}
        onConfirm={() => {
          if (toRemove === null) return
          removeLine.mutate(toRemove.id, { onSuccess: () => setToRemove(null) })
        }}
      />
    </div>
  )
}
