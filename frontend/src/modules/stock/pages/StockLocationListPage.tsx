import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { useAgencyOptions, useDepotOptions } from '@/modules/orders/hooks/useOrderScope'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

import { StockLocationTree } from '../components/StockLocationTree'
import { useStockLocations } from '../hooks/useStockLocations'
import type { StockLocation } from '../types/stock'
import type { StockLocationFilters } from '../types/stockFilters'
import { STOCK_LOCATION_SOURCE } from '../utils/stockSources'

/**
 * Emplacements de stock, en deux vues.
 *
 * **Liste** est la vue par défaut : paginée, filtrée et cherchée par le serveur,
 * c'est elle qui répond à « où est le code A-01-2 ».
 *
 * **Arbre** répond à une autre question — comment ce dépôt est rangé — et coûte
 * plus cher : `GET /stock-locations/tree` n'est pas paginé. Elle exige donc un
 * dépôt, et le dit plutôt que de charger le parc entier.
 *
 * Le filtre de dépôt est commun aux deux vues : passer de l'une à l'autre garde
 * le contexte, au lieu de redemander ce qui vient d'être choisi.
 */
export function StockLocationListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [agencyId, setAgencyId] = useState('')
  const [filters, setFilters] = useState<StockLocationFilters>({
    page: 1,
    perPage: 25,
    sort: 'location_code',
    direction: 'asc',
  })

  const agencies = useAgencyOptions()
  const depots = useDepotOptions(agencyId)
  const { data, isPending, error, refetch } = useStockLocations(filters)

  const patch = (next: Partial<StockLocationFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const text = (value: string | null) =>
    value === null || value === '' ? <span className="text-muted-foreground">—</span> : value

  const columns: Column<StockLocation>[] = [
    {
      key: 'locationCode',
      header: t('stock.fields.locationCode'),
      sortKey: 'location_code',
      cell: (row) => <span className="font-medium">{row.locationCode}</span>,
    },
    {
      key: 'zoneCode',
      header: t('stock.fields.zoneCode'),
      sortKey: 'zone_code',
      cell: (row) => text(row.zoneCode),
    },
    {
      key: 'aisle',
      header: t('stock.fields.aisle'),
      sortKey: 'aisle',
      hideOnMobile: true,
      cell: (row) => text(row.aisle),
    },
    {
      key: 'rack',
      header: t('stock.fields.rack'),
      sortKey: 'rack',
      hideOnMobile: true,
      cell: (row) => text(row.rack),
    },
    {
      key: 'level',
      header: t('stock.fields.level'),
      sortKey: 'level',
      hideOnMobile: true,
      cell: (row) => text(row.level),
    },
    {
      key: 'children',
      header: t('stock.fields.children'),
      hideOnMobile: true,
      cell: (row) => (row.childCount === undefined ? '—' : String(row.childCount)),
    },
    {
      key: 'status',
      header: t('stock.fields.status'),
      sortKey: 'status',
      cell: (row) => <StatusBadge status={row.status} source={STOCK_LOCATION_SOURCE} />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('stock.locations')}
        description={t('stock.locationsSubtitle')}
        actions={
          <PermissionGuard permission="stock_locations.create">
            <Button asChild>
              <Link to="/stock/locations/create">
                <Plus className="size-4" aria-hidden />
                {t('stock.newLocation')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row">
        <AsyncSelect
          label={t('orders.fields.agency')}
          value={agencyId}
          onChange={(next) => {
            setAgencyId(next)
            patch({ depotId: undefined })
          }}
          options={agencies.options}
          isLoading={agencies.isLoading}
        />
        <AsyncSelect
          label={t('orders.fields.depot')}
          value={filters.depotId ?? ''}
          onChange={(next) => patch({ depotId: next === '' ? undefined : next })}
          options={depots.options}
          isLoading={depots.isLoading}
          disabled={agencyId === ''}
          description={agencyId === '' ? t('stock.pickAgencyFirst') : undefined}
        />
      </div>

      <Tabs defaultValue="list">
        <TabsList>
          <TabsTrigger value="list">{t('stock.viewList')}</TabsTrigger>
          <TabsTrigger value="tree">{t('stock.viewTree')}</TabsTrigger>
        </TabsList>

        <TabsContent value="list" className="mt-6 flex flex-col gap-4">
          <div className="flex flex-col gap-3 sm:flex-row">
            <SearchInput
              value={filters.search ?? ''}
              onChange={(value) => patch({ search: value === '' ? undefined : value })}
              placeholder={t('stock.searchLocations')}
            />
            <StatusFilterSelect
              source={STOCK_LOCATION_SOURCE}
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
                direction:
                  current.sort === sortKey && current.direction === 'asc' ? 'desc' : 'asc',
              }))
            }
            onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
            onRetry={() => void refetch()}
            onRowClick={(row) => void navigate(`/stock/locations/${row.id}`)}
            emptyMessage={t('stock.noLocation')}
          />
        </TabsContent>

        <TabsContent value="tree" className="mt-6">
          <StockLocationTree depotId={filters.depotId ?? ''} />
        </TabsContent>
      </Tabs>
    </div>
  )
}
