import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { ReleaseReservationDialog } from '../components/ReleaseReservationDialog'
import { useStockReservation } from '../hooks/useStockReservations'
import { isReleased } from '../types/stock'
import { formatStockQuantity, STOCK_RESERVATION_SOURCE } from '../utils/stockSources'

/**
 * Fiche d'une réservation.
 *
 * **Aucune suppression.** La route ne l'expose pas, et c'est délibéré : une
 * réservation se libère, ce qui rend la quantité et laisse la trace. L'effacer
 * ferait disparaître ce qui avait été promis.
 *
 * Le bouton « Libérer » n'apparaît que si `releasedAt` est vide. C'est une
 * commodité, pas une garantie : le serveur refuse une seconde libération en
 * 409, contrôlée avant la transaction puis sous verrou.
 *
 * La ligne de commande n'est pas résolue : `StockReservationDetailResource`
 * n'expose que `orderLineId`, et il n'existe pas de route `GET /order-lines`.
 * L'identifiant est donc affiché tel quel plutôt que d'aller chercher toutes les
 * commandes pour en retrouver une.
 */
export function StockReservationDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams<{ id: string }>()
  const [releasing, setReleasing] = useState(false)

  const { data: reservation, isPending, error, refetch } = useStockReservation(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!reservation) return null

  const released = isReleased(reservation)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('stock.reservation')}
        description={formatDateTime(reservation.reservedAt)}
        actions={
          released ? null : (
            <PermissionGuard permission="stock_reservations.release">
              <Button variant="outline" onClick={() => setReleasing(true)}>
                {t('stock.release')}
              </Button>
            </PermissionGuard>
          )
        }
      />

      <SectionCard
        title={t('stock.sections.reservation')}
        description={released ? t('stock.releasedHint') : t('stock.heldHint')}
      >
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('stock.fields.articleCode')}>
            <Link to={`/stock/items/${reservation.stockItemId}`} className="hover:underline">
              {reservation.stockItem?.articleCode ?? reservation.stockItemId}
            </Link>
          </DetailField>
          <DetailField label={t('stock.fields.location')}>
            <Link
              to={`/stock/locations/${reservation.stockLocationId}`}
              className="hover:underline"
            >
              {reservation.stockLocation?.locationCode ?? reservation.stockLocationId}
            </Link>
          </DetailField>
          <DetailField label={t('stock.fields.quantity')}>
            {formatStockQuantity(reservation.quantity)}
          </DetailField>
          <DetailField label={t('stock.fields.status')}>
            <StatusBadge status={reservation.status} source={STOCK_RESERVATION_SOURCE} />
          </DetailField>
          <DetailField label={t('stock.fields.orderLine')}>
            {reservation.orderLineId}
          </DetailField>
          <DetailField label={t('stock.fields.reservedAt')}>
            {formatDateTime(reservation.reservedAt)}
          </DetailField>
          <DetailField label={t('stock.fields.releasedAt')}>
            {released ? formatDateTime(reservation.releasedAt) : t('stock.stillHeld')}
          </DetailField>
        </dl>
      </SectionCard>

      <ReleaseReservationDialog
        reservationId={id}
        open={releasing}
        onOpenChange={setReleasing}
        onReleased={() => void refetch()}
      />
    </div>
  )
}
