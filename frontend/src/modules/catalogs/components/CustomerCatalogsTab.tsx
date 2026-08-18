import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { useCatalogList, useDeleteCatalog } from '../hooks/useCatalogs'
import type { Catalog, CatalogFilters } from '../types/catalog'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { Button } from '@/shared/components/ui/button'

interface CustomerCatalogsTabProps {
  customerId: string
  /** Capacité `catalogEnabled` du client, telle que l'expose `CustomerResource`. */
  catalogEnabled: boolean
}

/**
 * Catalogues d'un client.
 *
 * Le catalogue est **facultatif** (§13) : lorsque la capacité est désactivée,
 * l'onglet le dit sans rien interdire ailleurs. Une commande reste créable avec
 * des lignes manuelles — présenter le catalogue comme un préalable serait faux.
 */
export function CustomerCatalogsTab({ customerId, catalogEnabled }: CustomerCatalogsTabProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [filters, setFilters] = useState<CatalogFilters>({ page: 1, perPage: 25 })
  const [deleting, setDeleting] = useState<Catalog | null>(null)

  const { data, isPending, error, refetch } = useCatalogList(customerId, filters)
  const remove = useDeleteCatalog(customerId)

  if (!catalogEnabled) {
    return (
      <EmptyState
        title={t('catalogs.disabledTitle')}
        description={t('catalogs.disabledHint')}
      />
    )
  }

  const columns: Column<Catalog>[] = [
    {
      key: 'code',
      header: t('catalogs.fields.code'),
      cell: (row) => (
        <Link
          to={`/customers/${customerId}/catalogs/${row.id}`}
          className="font-medium text-primary hover:underline"
        >
          {row.code}
        </Link>
      ),
    },
    { key: 'name', header: t('catalogs.fields.name'), cell: (row) => row.name },
    {
      key: 'itemCount',
      header: t('catalogs.fields.itemCount'),
      hideOnMobile: true,
      cell: (row) => t('catalogs.items', { count: row.itemCount }),
    },
    {
      key: 'status',
      header: t('catalogs.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(search) =>
            setFilters((current) => ({ ...current, page: 1, search: search || undefined }))
          }
        />

        <PermissionGuard permission="catalogs.create">
          <Button asChild size="sm">
            <Link to={`/customers/${customerId}/catalogs/create`}>
              <Plus className="size-4" aria-hidden />
              {t('catalogs.create')}
            </Link>
          </Button>
        </PermissionGuard>
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
        onRowClick={(row) => void navigate(`/customers/${customerId}/catalogs/${row.id}`)}
        emptyMessage={t('catalogs.empty')}
      />

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: deleting?.name ?? '' })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (deleting === null) return
          remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
        }}
      />
    </div>
  )
}
