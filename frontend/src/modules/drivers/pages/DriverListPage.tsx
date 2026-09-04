import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { useProviderOptions } from '@/modules/providers/hooks/useProviders'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { useDriverList } from '../hooks/useDrivers'
import type { Driver, DriverFilters } from '../types/driver'

/** Liste des chauffeurs, filtrable par fournisseur et par statut. */
export function DriverListPage() {
  const { t } = useTranslation()
  const providers = useProviderOptions()

  const [filters, setFilters] = useState<DriverFilters>({
    page: 1,
    perPage: 25,
    sort: 'code',
    direction: 'asc',
  })

  const { data, isPending, error, refetch } = useDriverList(filters)

  const patch = (next: Partial<DriverFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const columns: Column<Driver>[] = [
    {
      key: 'code',
      header: t('drivers.fields.code'),
      sortKey: 'code',
      cell: (row) => (
        <Link to={`/drivers/${row.id}`} className="font-medium text-primary hover:underline">
          {row.code}
        </Link>
      ),
    },
    { key: 'name', header: t('drivers.fields.name'), sortKey: 'name', cell: (row) => row.name },
    {
      key: 'provider',
      header: t('drivers.fields.provider'),
      hideOnMobile: true,
      cell: (row) => row.providerName ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'status',
      header: t('drivers.fields.status'),
      sortKey: 'status',
      cell: (row) => <StatusBadge status={row.status} source="driver" />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('drivers.title')}
        description={t('drivers.subtitle')}
        actions={
          <PermissionGuard permission="drivers.create">
            <Button asChild>
              <Link to="/drivers/create">
                <Plus className="size-4" aria-hidden />
                {t('drivers.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(search) => patch({ search: search || undefined })}
        />

        {/* `all` plutot qu'une chaine vide : `SelectItem` de Radix refuse une
            valeur vide, et un `providerId=` vide partirait dans l'URL. */}
        <AsyncSelect
          label={t('drivers.fields.provider')}
          value={filters.providerId ?? 'all'}
          onChange={(value) => patch({ providerId: value === 'all' ? undefined : value })}
          options={[{ value: 'all', label: t('common.all') }, ...providers.options]}
          isLoading={providers.isLoading}
        />

        <StatusFilterSelect
          source="driver"
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
        sort={filters.sort}
        direction={filters.direction}
        onSortChange={(sortKey) =>
          setFilters((current) => ({
            ...current,
            sort: sortKey,
            direction: current.sort === sortKey && current.direction === 'asc' ? 'desc' : 'asc',
          }))
        }
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        emptyMessage={t('drivers.empty')}
      />
    </div>
  )
}
