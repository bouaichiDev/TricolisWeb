import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { useAgencyList } from '../hooks/useAgencies'
import type { Agency, AgencyFilters } from '../types/agency'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

export function AgencyListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [filters, setFilters] = useState<AgencyFilters>({
    page: 1,
    perPage: 25,
    sort: 'name',
    direction: 'asc',
  })

  const { data, isPending, error, refetch } = useAgencyList(filters)

  const toggleSort = (sortKey: string) =>
    setFilters((current) => ({
      ...current,
      sort: sortKey,
      direction: current.sort === sortKey && current.direction === 'asc' ? 'desc' : 'asc',
    }))

  const columns: Column<Agency>[] = [
    {
      key: 'code',
      header: t('agencies.fields.code'),
      sortKey: 'code',
      cell: (row) => (
        <Link
          to={`/agencies/${row.id}`}
          className="font-medium text-primary hover:underline"
          onClick={(event) => event.stopPropagation()}
        >
          {row.code}
        </Link>
      ),
    },
    { key: 'name', header: t('agencies.fields.name'), sortKey: 'name', cell: (row) => row.name },
    {
      key: 'email',
      header: t('agencies.fields.email'),
      hideOnMobile: true,
      cell: (row) => row.email ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'status',
      header: t('agencies.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('agencies.title')}
        description={t('agencies.subtitle')}
        actions={
          <PermissionGuard permission="agencies.create">
            <Button asChild>
              <Link to="/agencies/create">
                <Plus className="size-4" aria-hidden />
                {t('agencies.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <SearchInput
        value={filters.search ?? ''}
        onChange={(search) => setFilters((c) => ({ ...c, page: 1, search: search || undefined }))}
      />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        sort={filters.sort}
        direction={filters.direction}
        onSortChange={toggleSort}
        onPageChange={(page) => setFilters((c) => ({ ...c, page }))}
        onRetry={() => void refetch()}
        onRowClick={(row) => void navigate(`/agencies/${row.id}`)}
      />
    </div>
  )
}
