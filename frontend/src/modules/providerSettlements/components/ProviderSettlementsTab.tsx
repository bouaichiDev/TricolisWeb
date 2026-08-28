import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { useProviderSettlements } from '../hooks/useSettlements'
import type { ProviderSettlement } from '../types/settlement'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDate, formatMoney } from '@/shared/utils/format'

interface ProviderSettlementsTabProps {
  providerId: string
  /** L'onglet ne charge qu'une fois ouvert : un onglet fermé ne coûte rien. */
  active: boolean
}

/**
 * Les décomptes d'un fournisseur, depuis sa fiche.
 *
 * Le §101 veut qu'on parte du fournisseur : c'est là qu'on sait ce qu'il a
 * roulé, et le bouton de création lui passe son identifiant plutôt que de le
 * faire rechoisir sur l'écran suivant.
 */
export function ProviderSettlementsTab({ providerId, active }: ProviderSettlementsTabProps) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)

  const { data, isPending, error, refetch } = useProviderSettlements(providerId, { page }, active)

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
  ]

  return (
    <SectionCard
      title={t('settlements.title')}
      actions={
        <PermissionGuard permission="provider_settlements.create">
          <Button asChild size="sm">
            <Link to={`/billing/settlements/create?provider=${providerId}`}>
              <Plus className="size-4" aria-hidden />
              {t('settlements.create')}
            </Link>
          </Button>
        </PermissionGuard>
      }
    >
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
    </SectionCard>
  )
}
