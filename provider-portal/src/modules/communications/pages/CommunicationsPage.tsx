import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'

import { errorMessage } from '@/shared/api/errors'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { formatDateTime } from '@/shared/utils/format'

import { communicationsApi, type Communication } from '../api/communicationsApi'

const columns: Column<Communication>[] = [
  {
    key: 'subject',
    header: 'Objet',
    cell: (row) => <Link className="font-medium underline-offset-4 hover:underline" to={`/communications/${row.id}`}>{row.subject ?? '(sans objet)'}</Link>,
  },
  { key: 'channel', header: 'Canal', hideOnMobile: true, cell: (row) => row.channelLabel },
  { key: 'sentAt', header: 'Envoyée', cell: (row) => formatDateTime(row.sentAt) },
  { key: 'status', header: 'Statut', cell: (row) => <StatusBadge code={row.status} label={row.statusLabel} /> },
]

/** Les messages que la plateforme vous a envoyés, du plus récent au plus ancien. Lecture seule. */
export function CommunicationsPage() {
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState<number | undefined>(undefined)
  const rows = useQuery({ queryKey: ['communications', page, perPage], queryFn: () => communicationsApi.list(page, perPage) })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Communications" description="Les messages qui vous ont été envoyés à votre adresse." />
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
        emptyMessage="Aucune communication reçue."
      />
    </div>
  )
}
