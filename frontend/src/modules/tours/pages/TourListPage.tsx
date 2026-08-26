import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { formatDate } from '@/shared/utils/format'

import { useTourList } from '../hooks/useTours'
import type { Tour, TourFilters } from '../types/tour'

/** Liste des tournées, tous états confondus. */
export function TourListPage() {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<TourFilters>({
    page: 1,
    perPage: 25,
    sort: 'tour_date',
    direction: 'desc',
  })

  const { data, isPending, error, refetch } = useTourList(filters)

  const patch = (next: Partial<TourFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const columns: Column<Tour>[] = [
    {
      key: 'tourNumber',
      header: t('tours.fields.tourNumber'),
      cell: (row) => (
        <Link to={`/tours/${row.id}`} className="font-medium text-primary hover:underline">
          {row.tourNumber}
        </Link>
      ),
    },
    {
      key: 'tourDate',
      header: t('tours.fields.tourDate'),
      cell: (row) => (row.tourDate === null ? '—' : formatDate(row.tourDate)),
    },
    {
      key: 'stops',
      header: t('tours.fields.stops'),
      hideOnMobile: true,
      cell: (row) => row.stopCount ?? '—',
    },
    {
      key: 'load',
      header: t('tours.fields.load'),
      hideOnMobile: true,
      cell: (row) =>
        t('tours.loadSummary', {
          packages: row.totalPackages,
          customers: row.totalCustomers,
        }),
    },
    {
      key: 'status',
      header: t('tours.fields.status'),
      cell: (row) => <StatusBadge status={row.status} source="tour" />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('tours.title')} description={t('tours.subtitle')} />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(search) => patch({ search: search || undefined })}
        />

        <StatusFilterSelect
          source="tour"
          value={filters.status}
          onChange={(status) => patch({ status })}
        />
      </div>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        emptyMessage={t('tours.empty')}
      />
    </div>
  )
}
