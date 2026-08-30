import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PriceListDialog } from '../components/PriceListDialog'
import { PriceListTable } from '../components/PriceListTable'
import { useDeletePriceList, usePriceLists } from '../hooks/usePricing'
import type { PriceList } from '../types/pricing'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

/**
 * Les barèmes négociés avec des clients.
 *
 * Ils priment sur le barème global (§169P), mais seulement là où ils
 * s'appliquent : un client qui a négocié la livraison garde le tarif général
 * pour le chargement.
 */
export function PricingCustomerPage() {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [toDelete, setToDelete] = useState<PriceList | null>(null)
  const [toEdit, setToEdit] = useState<PriceList | null>(null)

  const { data, isPending, error, refetch } = usePriceLists({
    page,
    scope: 'customer',
    search: search || undefined,
  })

  const remove = useDeletePriceList()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('pricing.customerLists.title')}
        description={t('pricing.customerLists.subtitle')}
        actions={
          <PermissionGuard permission="price_lists.create">
            <Button onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('pricing.lists.create.customer')}
            </Button>
          </PermissionGuard>
        }
      />

      <SearchInput
        value={search}
        onChange={(value) => {
          setSearch(value)
          setPage(1)
        }}
      />

      <PriceListTable
        rows={data?.data ?? []}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={setPage}
        onRetry={() => void refetch()}
        onDelete={setToDelete}
        onEdit={setToEdit}
        showCustomers
      />

      {creating ? (
        <PriceListDialog scope="customer" open onOpenChange={setCreating} />
      ) : null}

      {toEdit ? (
        <PriceListDialog
          scope="customer"
          priceList={toEdit}
          open
          onOpenChange={(open) => !open && setToEdit(null)}
        />
      ) : null}

      <ConfirmDialog
        open={toDelete !== null}
        onOpenChange={(open) => !open && setToDelete(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: toDelete?.code ?? '' })}
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
