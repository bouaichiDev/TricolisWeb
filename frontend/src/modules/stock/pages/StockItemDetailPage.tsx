import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import { StockBalanceTable } from '../components/StockBalanceTable'
import { StockMovementDialog } from '../components/StockMovementDialog'
import { StockMovementTable } from '../components/StockMovementTable'
import { useDeleteStockItem, useStockItem } from '../hooks/useStockItems'
import { STOCK_ITEM_SOURCE } from '../utils/stockSources'

/**
 * Fiche d'un article de stock.
 *
 * C'est **ici** que les quantités se lisent, et pas dans la liste : la liste ne
 * peut pas les montrer sans une requête par ligne, alors que `show` charge les
 * soldes de l'article en une fois.
 *
 * L'entrée de stock passe par un mouvement, jamais par l'écriture d'un solde —
 * d'où le bouton, et l'absence de tout champ de quantité modifiable.
 */
export function StockItemDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()
  const [recording, setRecording] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(false)

  const { data: item, isPending, error, refetch } = useStockItem(id)
  const remove = useDeleteStockItem()

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!item) return null

  const text = (value: string | null) =>
    value === null || value === '' ? <span className="text-muted-foreground">—</span> : value

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={item.articleCode}
        subtitle={item.description ?? item.customer?.name ?? undefined}
        editTo={`/stock/items/${id}/edit`}
        editPermission="stock_items.update"
        onDelete={() => setConfirmDelete(true)}
        deletePermission="stock_items.delete"
        actions={
          <PermissionGuard permission="stock_movements.create">
            <Button variant="outline" onClick={() => setRecording(true)}>
              {t('stock.newMovement')}
            </Button>
          </PermissionGuard>
        }
      />

      <SectionCard title={t('stock.sections.identity')}>
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('stock.fields.articleCode')}>{item.articleCode}</DetailField>
          <DetailField label={t('stock.fields.barcode')}>{text(item.barcode)}</DetailField>
          <DetailField label={t('stock.fields.customer')}>
            {item.customer?.name ?? item.customerName ?? item.customerId}
          </DetailField>
          <DetailField label={t('stock.fields.status')}>
            <StatusBadge status={item.status} source={STOCK_ITEM_SOURCE} />
          </DetailField>
          <DetailField label={t('stock.fields.description')}>{text(item.description)}</DetailField>
          <DetailField label={t('stock.fields.catalogItem')}>
            {item.catalogItemId === null ? t('stock.notLinked') : t('stock.linked')}
          </DetailField>
        </dl>
      </SectionCard>

      <SectionCard title={t('stock.balances')} description={t('stock.balancesHint')}>
        <StockBalanceTable stockItemId={id} />
      </SectionCard>

      <SectionCard title={t('stock.movements')} description={t('stock.movementsHint')}>
        <StockMovementTable stockItemId={id} />
      </SectionCard>

      <StockMovementDialog
        open={recording}
        onOpenChange={setRecording}
        stockItemId={id}
      />

      {/* Le serveur refuse en 409 tant qu'un solde, un mouvement ou une
          réservation s'y rattache : la confirmation le dit avant le clic. */}
      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('stock.deleteItemConfirm', { code: item.articleCode })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          remove.mutate(id, {
            onSuccess: () => {
              setConfirmDelete(false)
              void navigate('/stock/items')
            },
          })
        }}
      />
    </div>
  )
}
