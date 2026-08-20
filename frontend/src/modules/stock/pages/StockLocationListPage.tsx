import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { StockLocationDialog } from '../components/StockLocationDialog'
import { useDeleteStockLocation } from '../hooks/useStockLocationMutations'
import { useStockLocations } from '../hooks/useStock'
import type { StockLocation, StockLocationFilters } from '../types/stock'

/**
 * Emplacements de stock.
 *
 * Un mouvement a besoin d'un emplacement : sans cet écran, il n'y a aucun
 * endroit où ranger de la marchandise, et le dialogue de mouvement ouvre sur
 * une liste vide.
 *
 * Le backend expose aussi `stock-locations/tree`, la hiérarchie
 * parent/enfant. Une liste plate paginée est préférée ici : l'arbre entier
 * remonterait des milliers d'emplacements d'un coup, et ce dont on a besoin
 * pour un mouvement, c'est de retrouver un code — pas de parcourir un dépôt.
 */
export function StockLocationListPage() {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<StockLocationFilters>({ page: 1, perPage: 25 })
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<StockLocation | null>(null)
  const [deleting, setDeleting] = useState<StockLocation | null>(null)

  const { data, isPending, error, refetch } = useStockLocations(filters)
  const remove = useDeleteStockLocation()

  const text = (value: string | null) =>
    value === null || value === '' ? <span className="text-muted-foreground">—</span> : value

  const columns: Column<StockLocation>[] = [
    {
      key: 'locationCode',
      header: t('stock.fields.locationCode'),
      cell: (row) => <span className="font-medium">{row.locationCode}</span>,
    },
    { key: 'zoneCode', header: t('stock.fields.zoneCode'), cell: (row) => text(row.zoneCode) },
    {
      key: 'aisle',
      header: t('stock.fields.aisle'),
      hideOnMobile: true,
      cell: (row) => text(row.aisle),
    },
    {
      key: 'rack',
      header: t('stock.fields.rack'),
      hideOnMobile: true,
      cell: (row) => text(row.rack),
    },
    {
      key: 'level',
      header: t('stock.fields.level'),
      hideOnMobile: true,
      cell: (row) => text(row.level),
    },
    {
      key: 'barcode',
      header: t('stock.fields.barcode'),
      hideOnMobile: true,
      cell: (row) => text(row.barcode),
    },
    {
      key: 'status',
      header: t('stock.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <span className="flex justify-end gap-1">
          <PermissionGuard permission="stock_locations.update">
            <Button
              variant="ghost"
              size="icon"
              title={t('common.edit')}
              aria-label={t('common.edit')}
              onClick={() => setEditing(row)}
            >
              <Pencil className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>

          <PermissionGuard permission="stock_locations.delete">
            <Button
              variant="ghost"
              size="icon"
              title={t('common.delete')}
              aria-label={t('common.delete')}
              onClick={() => setDeleting(row)}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
        </span>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('stock.locations')}
        description={t('stock.locationsSubtitle')}
        actions={
          <PermissionGuard permission="stock_locations.create">
            <Button size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('stock.newLocation')}
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
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        emptyMessage={t('stock.noLocation')}
      />

      {creating || editing !== null ? (
        <StockLocationDialog
          key={editing?.id ?? 'new'}
          location={editing}
          open
          onOpenChange={(open) => {
            if (open) return
            setCreating(false)
            setEditing(null)
          }}
        />
      ) : null}

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('confirm.deleteTitle')}
        description={t('stock.deleteLocationConfirm', { code: deleting?.locationCode ?? '' })}
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
