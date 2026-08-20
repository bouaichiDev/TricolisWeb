import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { Button } from '@/shared/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/shared/components/ui/sheet'

import { StockBalanceTable } from './StockBalanceTable'
import { StockMovementDialog } from './StockMovementDialog'
import { StockMovementTable } from './StockMovementTable'
import { useCreateStockItem, useStockItemOfCatalogItem } from '../hooks/useStock'

interface CatalogItemStockSheetProps {
  customerId: string
  /** L'article de catalogue ouvert, `null` quand le tiroir est fermé. */
  item: { id: string; articleCode: string; name: string; barcode: string | null } | null
  onOpenChange: (open: boolean) => void
}

/**
 * Stock d'un article de catalogue.
 *
 * L'article de catalogue **décrit** une référence ; il n'en porte aucune
 * quantité, et ne doit pas en porter. Le pont vers le stock est `StockItem`,
 * dont `catalogItemId` est facultatif : un article catalogué n'est pas
 * forcément suivi en dépôt, et de la marchandise peut arriver sans figurer au
 * catalogue.
 *
 * Le tiroir traite donc deux cas. Sans `StockItem`, il propose d'en créer un —
 * c'est ce qui met l'article sous suivi. Avec, il montre les soldes par
 * emplacement puis l'historique des mouvements, et l'entrée de stock se fait
 * par un mouvement, jamais en écrivant un solde.
 */
export function CatalogItemStockSheet({
  customerId,
  item,
  onOpenChange,
}: CatalogItemStockSheetProps) {
  const { t } = useTranslation()
  const [recording, setRecording] = useState(false)

  const open = item !== null
  const { item: stockItem, isPending } = useStockItemOfCatalogItem(item?.id ?? '', open)
  const create = useCreateStockItem(customerId)

  const track = () => {
    if (item === null) return

    void create.mutateAsync({
      customerId,
      catalogItemId: item.id,
      articleCode: item.articleCode,
      barcode: item.barcode,
      description: item.name,
      status: 'active',
    })
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-3xl">
        <SheetHeader>
          <SheetTitle>{t('stock.itemTitle', { name: item?.name ?? '' })}</SheetTitle>
          <SheetDescription>{t('stock.itemHint')}</SheetDescription>
        </SheetHeader>

        {isPending ? null : stockItem === null ? (
          <div className="px-4 pb-4">
            <EmptyState title={t('stock.notTracked')} description={t('stock.notTrackedHint')} />

            <PermissionGuard permission="stock_items.create">
              <Button
                type="button"
                className="mt-3"
                onClick={track}
                disabled={create.isPending}
              >
                <Plus className="size-4" aria-hidden />
                {t('stock.track')}
              </Button>
            </PermissionGuard>
          </div>
        ) : (
          <div className="flex flex-col gap-6 px-4 pb-6">
            <section className="flex flex-col gap-2">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="font-semibold">{t('stock.balances')}</h3>

                <PermissionGuard permission="stock_movements.create">
                  <Button type="button" size="sm" onClick={() => setRecording(true)}>
                    <Plus className="size-4" aria-hidden />
                    {t('stock.newMovement')}
                  </Button>
                </PermissionGuard>
              </div>

              <StockBalanceTable stockItemId={stockItem.id} />
            </section>

            <section className="flex flex-col gap-2 border-t pt-4">
              <h3 className="font-semibold">{t('stock.movements')}</h3>
              <StockMovementTable stockItemId={stockItem.id} />
            </section>
          </div>
        )}

        {recording && stockItem !== null ? (
          <StockMovementDialog stockItemId={stockItem.id} open onOpenChange={setRecording} />
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
