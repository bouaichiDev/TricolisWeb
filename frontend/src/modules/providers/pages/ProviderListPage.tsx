import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { useProviderList } from '../hooks/useProviders'
import type { Provider, ProviderFilters } from '../types/provider'

/**
 * Liste des fournisseurs.
 *
 * Recherche, filtre et tri partent au serveur : trier localement les 25 lignes
 * d'une page donnerait un ordre faux dès la seconde.
 *
 * Le filtre de statut vient du référentiel, pas d'une liste codée ici — c'est
 * la règle transversale de cette phase.
 */
export function ProviderListPage() {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<ProviderFilters>({
    page: 1,
    perPage: 25,
    sort: 'code',
    direction: 'asc',
  })

  const { data, isPending, error, refetch } = useProviderList(filters)

  const patch = (next: Partial<ProviderFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const toggleSort = (sortKey: string) =>
    setFilters((current) => ({
      ...current,
      sort: sortKey,
      direction: current.sort === sortKey && current.direction === 'asc' ? 'desc' : 'asc',
    }))

  const columns: Column<Provider>[] = [
    {
      key: 'code',
      header: t('providers.fields.code'),
      sortKey: 'code',
      cell: (row) => (
        <Link to={`/providers/${row.id}`} className="font-medium text-primary hover:underline">
          {row.code}
        </Link>
      ),
    },
    { key: 'name', header: t('providers.fields.name'), sortKey: 'name', cell: (row) => row.name },
    {
      key: 'driverCount',
      header: t('providers.fields.driverCount'),
      hideOnMobile: true,
      cell: (row) => row.driverCount ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'vehicleCount',
      header: t('providers.fields.vehicleCount'),
      hideOnMobile: true,
      cell: (row) => row.vehicleCount ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'status',
      header: t('providers.fields.status'),
      sortKey: 'status',
      cell: (row) => <StatusBadge status={row.status} source="provider" />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('providers.title')}
        description={t('providers.subtitle')}
        actions={
          <PermissionGuard permission="providers.create">
            <Button asChild>
              <Link to="/providers/create">
                <Plus className="size-4" aria-hidden />
                {t('providers.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(search) => patch({ search: search || undefined })}
        />

        <StatusFilterSelect
          source="provider"
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
        onSortChange={toggleSort}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        emptyMessage={t('providers.empty')}
      />
    </div>
  )
}
