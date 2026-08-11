import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { CustomerCapabilities } from '../components/CustomerCapabilities'
import { useCustomerList } from '../hooks/useCustomers'
import type { Customer, CustomerFilters } from '../types/customer'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

const STATUSES = ['active', 'inactive', 'blocked'] as const

/**
 * Liste des clients.
 *
 * Recherche, tri et pagination sont **entièrement serveur** : les filtres
 * partent dans l'URL de l'API, jamais appliqués sur la page reçue. Trier
 * localement les 25 lignes d'une page donnerait un ordre faux dès la seconde.
 */
export function CustomerListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [filters, setFilters] = useState<CustomerFilters>({
    page: 1,
    perPage: 25,
    sort: 'name',
    direction: 'asc',
  })

  const { data, isPending, error, refetch } = useCustomerList(filters)

  const patch = (next: Partial<CustomerFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const toggleSort = (sortKey: string) =>
    setFilters((current) => ({
      ...current,
      sort: sortKey,
      direction: current.sort === sortKey && current.direction === 'asc' ? 'desc' : 'asc',
    }))

  const columns: Column<Customer>[] = [
    {
      key: 'code',
      header: t('customers.fields.code'),
      sortKey: 'code',
      cell: (row) => (
        <Link
          to={`/customers/${row.id}`}
          className="font-medium text-primary hover:underline"
          onClick={(event) => event.stopPropagation()}
        >
          {row.code}
        </Link>
      ),
    },
    {
      key: 'name',
      header: t('customers.fields.name'),
      sortKey: 'name',
      cell: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.name}</p>
          {row.legalName ? (
            <p className="truncate text-xs text-muted-foreground">{row.legalName}</p>
          ) : null}
        </div>
      ),
    },
    {
      key: 'email',
      header: t('customers.fields.email'),
      hideOnMobile: true,
      cell: (row) => row.email ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'capabilities',
      header: t('customers.capabilities'),
      hideOnMobile: true,
      cell: (row) => <CustomerCapabilities customer={row} variant="compact" />,
    },
    {
      key: 'status',
      header: t('customers.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('customers.title')}
        description={t('customers.subtitle')}
        actions={
          <PermissionGuard permission="customers.create">
            <Button asChild>
              <Link to="/customers/create">
                <Plus className="size-4" aria-hidden />
                {t('customers.create')}
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

        <Select
          value={filters.status ?? 'all'}
          onValueChange={(value) => patch({ status: value === 'all' ? undefined : value })}
        >
          <SelectTrigger className="w-full sm:w-48">
            <SelectValue placeholder={t('customers.fields.status')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t('common.all')}</SelectItem>
            {STATUSES.map((status) => (
              <SelectItem key={status} value={status}>
                {t(`status.${status}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
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
        onRowClick={(row) => void navigate(`/customers/${row.id}`)}
      />
    </div>
  )
}
