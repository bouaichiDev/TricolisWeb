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
 * Les barèmes qui s'appliquent à tous les clients.
 *
 * C'est le socle : un client sans tarif propre est facturé d'après ces
 * règles. Un barème client ne les remplace pas entièrement — il ne prime que
 * là où il dit quelque chose, et le repli reste ici pour le reste.
 */
export function PricingGlobalPage() {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [toDelete, setToDelete] = useState<PriceList | null>(null)

  const { data, isPending, error, refetch } = usePriceLists({
    page,
    scope: 'global',
    search: search || undefined,
  })

  const remove = useDeletePriceList()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('pricing.globalLists.title')}
        description={t('pricing.globalLists.subtitle')}
        actions={
          <PermissionGuard permission="price_lists.create">
            <Button onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('pricing.lists.create.global')}
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
        showCustomers={false}
      />

      {creating ? (
        <PriceListDialog scope="global" open onOpenChange={setCreating} />
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
