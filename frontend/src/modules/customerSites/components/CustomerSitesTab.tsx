import { Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { useCustomerSiteList, useDeleteCustomerSite } from '../hooks/useCustomerSites'
import type { CustomerSite } from '../types/customerSite'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

/**
 * Sites d'un client.
 *
 * Le §21 demande de vérifier côté frontend que `site.customerId` correspond à
 * la route. La liste étant servie par une route imbriquée, le backend l'assure
 * déjà ; le filtre ci-dessous est une seconde barrière, sans se substituer à
 * la première.
 */
export function CustomerSitesTab({ customerId }: { customerId: string }) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const [toDelete, setToDelete] = useState<CustomerSite | null>(null)

  const { data, isPending, error, refetch } = useCustomerSiteList(customerId, page)
  const remove = useDeleteCustomerSite(customerId)

  const rows = (data?.data ?? []).filter((site) => site.customerId === customerId)

  const columns: Column<CustomerSite>[] = [
    {
      key: 'code',
      header: t('customerSites.fields.code'),
      cell: (row) => (
        <Link
          to={`/customers/${customerId}/sites/${row.id}`}
          className="font-medium text-primary hover:underline"
        >
          {row.code}
        </Link>
      ),
    },
    {
      key: 'name',
      header: t('customerSites.fields.name'),
      cell: (row) => (
        <span className="flex items-center gap-2">
          {row.name}
          {row.isDefault ? (
            <Badge variant="secondary" className="font-normal">
              {t('customerSites.default')}
            </Badge>
          ) : null}
        </span>
      ),
    },
    {
      key: 'siteType',
      header: t('customerSites.fields.siteType'),
      hideOnMobile: true,
      cell: (row) => row.siteType ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'status',
      header: t('customerSites.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-12',
      cell: (row) => (
        <PermissionGuard permission="customer_sites.delete">
          <Button
            variant="ghost"
            size="icon"
            aria-label={t('common.delete')}
            onClick={() => setToDelete(row)}
          >
            <Trash2 className="size-4" aria-hidden />
          </Button>
        </PermissionGuard>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      <PermissionGuard permission="customer_sites.create">
        <div className="flex justify-end">
          <Button asChild size="sm">
            <Link to={`/customers/${customerId}/sites/create`}>
              <Plus className="size-4" aria-hidden />
              {t('customerSites.create')}
            </Link>
          </Button>
        </div>
      </PermissionGuard>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={setPage}
        onRetry={() => void refetch()}
        emptyMessage={t('customerSites.empty')}
      />

      <ConfirmDialog
        open={toDelete !== null}
        onOpenChange={(open) => !open && setToDelete(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: toDelete?.name ?? '' })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (toDelete === null) return
          remove.mutate(toDelete.id, { onSuccess: () => setToDelete(null) })
        }}
      />
    </div>
  )
}
