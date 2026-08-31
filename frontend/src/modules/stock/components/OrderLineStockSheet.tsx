import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { Button } from '@/shared/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/shared/components/ui/sheet'

import { OrderLineReservations } from './OrderLineReservations'
import { formatStockQuantity } from '../utils/stockSources'

interface OrderLineStockSheetProps {
  /** La ligne ouverte, `null` quand le tiroir est fermé. */
  line: {
    id: string
    name: string
    articleCode: string | null
    quantity: number | string
    reservedQuantity: number | string | null
  } | null
  onOpenChange: (open: boolean) => void
}

/**
 * Stock et réservations d'une ligne de commande.
 *
 * En tiroir, et pas en colonne : les réservations d'une ligne se demandent par
 * `GET /stock-reservations?orderLineId=`, une requête par ligne. Les afficher
 * toutes dans le tableau ferait autant d'appels que de lignes — le N+1 que le
 * §68 interdit. Ouvrir une ligne n'en fait qu'un, au moment où on le demande.
 *
 * `reservedQuantity` est calculée par le serveur à partir des réservations : le
 * total en tête et le détail en dessous viennent donc de la même source, et ne
 * peuvent pas diverger.
 */
export function OrderLineStockSheet({ line, onOpenChange }: OrderLineStockSheetProps) {
  const { t } = useTranslation()
  const open = line !== null

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
        {line === null ? null : (
          <>
            <SheetHeader>
              <SheetTitle>{t('stock.lineStockTitle', { name: line.name })}</SheetTitle>
              <SheetDescription>{t('stock.lineStockHint')}</SheetDescription>
            </SheetHeader>

            <div className="flex flex-col gap-6 px-4 pb-6">
              <dl className="grid grid-cols-2 gap-4">
                <div>
                  <dt className="text-xs text-muted-foreground">{t('orders.fields.quantity')}</dt>
                  <dd className="text-lg font-semibold tabular-nums">
                    {formatStockQuantity(line.quantity)}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">
                    {t('orders.fields.reservedQuantity')}
                  </dt>
                  <dd className="text-lg font-semibold tabular-nums">
                    {formatStockQuantity(line.reservedQuantity)}
                  </dd>
                </div>
              </dl>

              <div className="flex flex-col gap-2">
                <p className="text-sm font-medium">{t('stock.reservations')}</p>
                <OrderLineReservations orderLineId={line.id} />
              </div>

              <PermissionGuard permission="stock_reservations.create">
                <Button variant="outline" asChild>
                  <Link to="/stock/reservations/create">{t('stock.newReservation')}</Link>
                </Button>
              </PermissionGuard>
            </div>
          </>
        )}
      </SheetContent>
    </Sheet>
  )
}
