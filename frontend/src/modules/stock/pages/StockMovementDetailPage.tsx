import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'

import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDateTime } from '@/shared/utils/format'

import { useStockMovement } from '../hooks/useStockMovements'
import { movementDirection } from '../types/stock'
import { formatStockQuantity } from '../utils/stockSources'

/**
 * Fiche d'un mouvement.
 *
 * **Aucune action.** Ni modifier, ni supprimer : la route ne les expose pas, et
 * l'écran n'a pas à proposer des boutons qui reviendraient en 405. Ce qui est
 * écrit ici a eu lieu.
 *
 * `sourceEntityType` / `sourceEntityId` disent d'où vient le mouvement — une
 * commande, un colis. Le type est une clé de `MorphMap`, pas un nom de classe :
 * il est affiché tel quel, faute d'une route générique qui saurait le résoudre.
 */
export function StockMovementDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams<{ id: string }>()

  const { data: movement, isPending, error, refetch } = useStockMovement(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!movement) return null

  const direction = movementDirection(movement)
  const dash = <span className="text-muted-foreground">—</span>

  const locationLink = (
    id: string | null,
    compact: { id: string; locationCode: string } | null | undefined,
  ) => {
    if (id === null) return dash

    return (
      <Link to={`/stock/locations/${id}`} className="hover:underline">
        {compact?.locationCode ?? id}
      </Link>
    )
  }

  const creator = movement.creator
  const creatorName =
    creator === null || creator === undefined
      ? null
      : [creator.firstName, creator.lastName].filter(Boolean).join(' ') || creator.email

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t(`stock.directions.${direction}`)}
        description={formatDateTime(movement.createdAt)}
      />

      <SectionCard title={t('stock.sections.movement')} description={t('stock.immutableHint')}>
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('stock.fields.articleCode')}>
            <Link to={`/stock/items/${movement.stockItemId}`} className="hover:underline">
              {movement.stockItem?.articleCode ?? movement.stockItemId}
            </Link>
          </DetailField>
          <DetailField label={t('stock.fields.movementType')}>{movement.movementType}</DetailField>
          <DetailField label={t('stock.fields.quantity')}>
            {formatStockQuantity(movement.quantity)}
          </DetailField>
          <DetailField label={t('stock.fields.direction')}>
            {t(`stock.directions.${direction}`)}
          </DetailField>
          <DetailField label={t('stock.sourceLocation')}>
            {locationLink(movement.sourceLocationId, movement.sourceLocation)}
          </DetailField>
          <DetailField label={t('stock.destinationLocation')}>
            {locationLink(movement.destinationLocationId, movement.destinationLocation)}
          </DetailField>
        </dl>
      </SectionCard>

      <SectionCard title={t('stock.sections.origin')} description={t('stock.originHint')}>
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('stock.fields.sourceEntityType')}>
            {movement.sourceEntityType ?? dash}
          </DetailField>
          <DetailField label={t('stock.fields.sourceEntityId')}>
            {movement.sourceEntityId ?? dash}
          </DetailField>
          <DetailField label={t('stock.fields.createdBy')}>{creatorName ?? dash}</DetailField>
          <DetailField label={t('stock.fields.createdAt')}>
            {formatDateTime(movement.createdAt)}
          </DetailField>
        </dl>
      </SectionCard>
    </div>
  )
}
