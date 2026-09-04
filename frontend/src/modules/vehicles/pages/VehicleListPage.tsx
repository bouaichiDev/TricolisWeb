import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { useProviderOptions } from '@/modules/providers/hooks/useProviders'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { useTypeItemOptions } from '@/modules/types/hooks/useTypes'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { useVehicleList } from '../hooks/useVehicles'
import type { Vehicle, VehicleFilters } from '../types/vehicle'

/**
 * Liste des véhicules.
 *
 * Les capacités sont regroupées dans une seule colonne : trois colonnes de
 * chiffres se ressemblent, et sur mobile elles disparaîtraient toutes. Le
 * détail les reprend une par une.
 */
export function VehicleListPage() {
  const { t } = useTranslation()
  const providers = useProviderOptions()
  const types = useTypeItemOptions('vehicle')

  const [filters, setFilters] = useState<VehicleFilters>({
    page: 1,
    perPage: 25,
    sort: 'code',
    direction: 'asc',
  })

  const { data, isPending, error, refetch } = useVehicleList(filters)

  const patch = (next: Partial<VehicleFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const columns: Column<Vehicle>[] = [
    {
      key: 'code',
      header: t('vehicles.fields.code'),
      sortKey: 'code',
      cell: (row) => (
        <Link to={`/vehicles/${row.id}`} className="font-medium text-primary hover:underline">
          {row.code}
        </Link>
      ),
    },
    {
      key: 'registrationNumber',
      header: t('vehicles.fields.registrationNumber'),
      sortKey: 'registration_number',
      cell: (row) => row.registrationNumber,
    },
    {
      key: 'provider',
      header: t('vehicles.fields.provider'),
      hideOnMobile: true,
      cell: (row) => row.providerName ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'vehicleType',
      header: t('vehicles.fields.vehicleType'),
      hideOnMobile: true,
      cell: (row) => row.vehicleTypeName ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'capacities',
      header: t('vehicles.capacities'),
      hideOnMobile: true,
      cell: (row) => (
        <span className="text-sm text-muted-foreground">
          {t('vehicles.capacitySummary', {
            payload: row.payloadCapacity,
            volume: row.volumeCapacity,
            pallets: row.palletCapacity,
          })}
        </span>
      ),
    },
    {
      key: 'status',
      header: t('vehicles.fields.status'),
      sortKey: 'status',
      cell: (row) => <StatusBadge status={row.status} source="vehicle" />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('vehicles.title')}
        description={t('vehicles.subtitle')}
        actions={
          <PermissionGuard permission="vehicles.create">
            <Button asChild>
              <Link to="/vehicles/create">
                <Plus className="size-4" aria-hidden />
                {t('vehicles.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(search) => patch({ search: search || undefined })}
        />

        <AsyncSelect
          label={t('vehicles.fields.provider')}
          value={filters.providerId ?? 'all'}
          onChange={(value) => patch({ providerId: value === 'all' ? undefined : value })}
          options={[{ value: 'all', label: t('common.all') }, ...providers.options]}
          isLoading={providers.isLoading}
        />

        <AsyncSelect
          label={t('vehicles.fields.vehicleType')}
          value={filters.vehicleTypeId ?? 'all'}
          onChange={(value) => patch({ vehicleTypeId: value === 'all' ? undefined : value })}
          options={[{ value: 'all', label: t('common.all') }, ...types.options]}
          isLoading={types.isLoading}
        />

        <StatusFilterSelect
          source="vehicle"
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
        emptyMessage={t('vehicles.empty')}
      />
    </div>
  )
}
