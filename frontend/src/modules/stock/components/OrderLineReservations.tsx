import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDateTime } from '@/shared/utils/format'

import { useStockLocationOptions } from '../hooks/useStockScope'
import { useStockReservations } from '../hooks/useStockReservations'
import { isReleased } from '../types/stock'
import { formatStockQuantity, STOCK_RESERVATION_SOURCE } from '../utils/stockSources'

interface OrderLineReservationsProps {
  orderLineId: string
}

/**
 * Ce qui est réservé pour une ligne de commande.
 *
 * Une ligne peut porter **plusieurs** réservations : sa marchandise peut dormir
 * dans deux emplacements, et il faut alors en prendre dans chacun. C'est
 * pourquoi il n'existe pas de `StockReservationLine` — la réservation elle-même
 * est déjà la granularité « une quantité, un emplacement ».
 *
 * Les réservations libérées restent affichées, en retrait : elles expliquent
 * pourquoi `reservedQuantity` a baissé, ce qu'une liste amputée laisserait
 * chercher.
 */
export function OrderLineReservations({ orderLineId }: OrderLineReservationsProps) {
  const { t } = useTranslation()
  // `StockReservationListResource` ne renvoie que `stockLocationId` : le code
  // lisible vient de la liste des emplacements, chargée une fois pour toutes.
  const locations = useStockLocationOptions()

  const { data, isPending } = useStockReservations(
    { page: 1, perPage: 25, orderLineId, sort: 'reserved_at', direction: 'desc' },
    orderLineId !== '',
  )

  const rows = data?.data ?? []

  if (isPending) {
    return <p className="text-xs text-muted-foreground">{t('common.loading')}</p>
  }

  if (rows.length === 0) {
    return <p className="text-xs text-muted-foreground">{t('stock.noReservationForLine')}</p>
  }

  return (
    <ul className="flex flex-col gap-1.5">
      {rows.map((reservation) => {
        const released = isReleased(reservation)

        return (
          <li
            key={reservation.id}
            className={`flex flex-wrap items-center gap-2 text-xs ${
              released ? 'text-muted-foreground' : ''
            }`}
          >
            <Link to={`/stock/reservations/${reservation.id}`} className="hover:underline">
              {formatStockQuantity(reservation.quantity)}
            </Link>
            <span>
              {t('stock.atLocation', {
                location:
                  locations.options.find(
                    (option) => option.value === reservation.stockLocationId,
                  )?.label ?? reservation.stockLocationId,
              })}
            </span>
            <StatusBadge status={reservation.status} source={STOCK_RESERVATION_SOURCE} />
            {released ? <span>{t('stock.releasedOn', {
              date: formatDateTime(reservation.releasedAt),
            })}</span> : null}
          </li>
        )
      })}
    </ul>
  )
}
