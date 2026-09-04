import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { useServiceList } from '../hooks/useServices'
import type { Service, ServiceFilters } from '../types/service'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

/** Tri accepté par `ServiceController::index` ; toute autre colonne renvoie 422. */
const SORTABLE = ['code', 'name']

export function ServiceListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [filters, setFilters] = useState<ServiceFilters>({ page: 1, perPage: 25 })

  const { data, isPending, error, refetch } = useServiceList(filters)

  const columns: Column<Service>[] = [
    {
      key: 'code',
      header: t('services.fields.code'),
      sortKey: 'code',
      cell: (row) => (
        <Link
          to={`/services/${row.id}`}
          className="font-medium text-primary hover:underline"
          onClick={(event) => event.stopPropagation()}
        >
          {row.code}
        </Link>
      ),
    },
    { key: 'name', header: t('services.fields.name'), sortKey: 'name', cell: (row) => row.name },
    {
      key: 'unit',
      header: t('services.fields.unit'),
      hideOnMobile: true,
      cell: (row) => row.unit ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'duration',
      header: t('services.fields.defaultDurationMinutes'),
      hideOnMobile: true,
      cell: (row) =>
        row.defaultDurationMinutes === null ? (
          <span className="text-muted-foreground">—</span>
        ) : (
          t('services.minutes', { count: row.defaultDurationMinutes })
        ),
    },
    {
      key: 'flags',
      header: t('services.fields.billableToCustomer'),
      hideOnMobile: true,
      cell: (row) => (
        <span className="flex flex-wrap gap-1">
          {row.billableToCustomer ? (
            <Badge variant="secondary" className="font-normal">
              {t('services.billable')}
            </Badge>
          ) : null}
          {row.payableToProvider ? (
            <Badge variant="outline" className="font-normal">
              {t('services.payable')}
            </Badge>
          ) : null}
        </span>
      ),
    },
    {
      key: 'status',
      header: t('services.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('services.title')}
        description={t('services.subtitle')}
        actions={
          <PermissionGuard permission="services.create">
            <Button asChild>
              <Link to="/services/create">
                <Plus className="size-4" aria-hidden />
                {t('services.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <SearchInput
        value={filters.search ?? ''}
        onChange={(search) =>
          setFilters((current) => ({ ...current, page: 1, search: search || undefined }))
        }
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
        onSortChange={(sortKey) =>
          SORTABLE.includes(sortKey) &&
          setFilters((current) => ({
            ...current,
            sort: sortKey,
            direction: current.sort === sortKey && current.direction === 'asc' ? 'desc' : 'asc',
          }))
        }
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        onRowClick={(row) => void navigate(`/services/${row.id}`)}
      />
    </div>
  )
}
