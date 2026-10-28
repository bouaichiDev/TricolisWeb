import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'

import { errorMessage } from '@/shared/api/errors'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { formatDate } from '@/shared/utils/format'

import { money, settlementsApi, type Settlement } from '../api/settlementsApi'

const columns: Column<Settlement>[] = [
  {
    key: 'number',
    header: 'Décompte',
    cell: (row) => <Link className="font-medium underline-offset-4 hover:underline" to={`/settlements/${row.id}`}>{row.settlementNumber}</Link>,
  },
  { key: 'period', header: 'Période', hideOnMobile: true, cell: (row) => `${formatDate(row.periodFrom)} → ${formatDate(row.periodTo)}` },
  { key: 'total', header: 'Total', cell: (row) => money(row.total) },
  { key: 'status', header: 'Statut', cell: (row) => <StatusBadge code={row.status} label={row.statusLabel} /> },
]

/** Les décomptes clôturés par votre transporteur. Montants calculés par lui ; rien ne se modifie ici. */
export function SettlementsPage() {
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState<number | undefined>(undefined)
  const rows = useQuery({ queryKey: ['settlements', page, perPage], queryFn: () => settlementsApi.list(page, perPage) })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Décomptes" description="Les décomptes clôturés de vos prestations." />
      <DataTable
        columns={columns}
        rows={rows.data?.data ?? []}
        rowKey={(row) => row.id}
        meta={rows.data?.meta}
        isLoading={rows.isPending}
        errorMessage={rows.isError ? errorMessage(rows.error) : null}
        onRetry={() => void rows.refetch()}
        onPageChange={setPage}
        onPerPageChange={(size) => { setPerPage(size); setPage(1) }}
        emptyMessage="Aucun décompte clôturé."
      />
    </div>
  )
}
