import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { ALL_CUSTOMERS, InvoiceFilterBar, type InvoiceFilterState } from '../components/InvoiceFilterBar'
import { InvoiceTable } from '../components/InvoiceTable'
import { useDeleteInvoice, useInvoiceList } from '../hooks/useInvoices'
import type { Invoice } from '../types/invoice'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

const INITIAL: InvoiceFilterState = {
  customerId: ALL_CUSTOMERS,
  search: '',
  status: undefined,
  invoiceDateFrom: '',
  invoiceDateTo: '',
}

/**
 * Liste des factures clients.
 *
 * Le filtre client est facultatif : contrairement à la création, consulter
 * n'exige pas de désigner un client — un facturier cherche souvent un numéro
 * dont il ne sait plus à qui il appartient.
 */
export function InvoiceListPage() {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<InvoiceFilterState>(INITIAL)
  const [page, setPage] = useState(1)
  const [toDelete, setToDelete] = useState<Invoice | null>(null)

  const { data, isPending, error, refetch } = useInvoiceList({
    page,
    search: filters.search || undefined,
    customerId: filters.customerId === ALL_CUSTOMERS ? undefined : filters.customerId,
    status: filters.status,
    invoiceDateFrom: filters.invoiceDateFrom || undefined,
    invoiceDateTo: filters.invoiceDateTo || undefined,
  })

  const remove = useDeleteInvoice()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('billing.invoices.title')}
        description={t('billing.invoices.subtitle')}
        actions={
          <PermissionGuard permission="invoices.create">
            <Button asChild>
              <Link to="/billing/invoices/create">
                <Plus className="size-4" aria-hidden />
                {t('billing.invoices.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <InvoiceFilterBar
        value={filters}
        onChange={(next) => {
          setFilters(next)
          setPage(1)
        }}
      />

      <InvoiceTable
        rows={data?.data ?? []}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={setPage}
        onRetry={() => void refetch()}
        onDelete={setToDelete}
      />

      <ConfirmDialog
        open={toDelete !== null}
        onOpenChange={(open) => !open && setToDelete(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: toDelete?.invoiceNumber ?? '' })}
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
