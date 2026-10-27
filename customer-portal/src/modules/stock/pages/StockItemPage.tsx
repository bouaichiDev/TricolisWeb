import { MessageSquareWarning, Pencil } from 'lucide-react'
import { useState } from 'react'
import { useParams } from 'react-router-dom'

import { ApiError, errorMessage } from '@/shared/api/errors'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import { AdjustmentRequestDialog } from '../components/AdjustmentRequestDialog'
import { StockBalancesSection } from '../components/StockBalancesSection'
import { StockItemDialog } from '../components/StockItemDialog'
import { StockMovementsTable } from '../components/StockMovementsTable'
import { StockReservationsTable } from '../components/StockReservationsTable'
import { StockTotalsCards } from '../components/StockTotalsCards'
import { useStockItem } from '../hooks/useStock'

/**
 * Le détail d'un article (§12) : informations, totaux, répartition,
 * mouvements, réservations et commandes liées — chaque bloc selon les
 * capacités rendues par le backend. « Demander une correction » n'apparaît
 * qu'avec `canRequestChange`, et ne modifie jamais le stock.
 */
export function StockItemPage() {
  const { id = '' } = useParams()
  const item = useStockItem(id)
  const [editing, setEditing] = useState(false)
  const [adjusting, setAdjusting] = useState(false)
  const [movementsPage, setMovementsPage] = useState(1)
  const [reservationsPage, setReservationsPage] = useState(1)

  if (item.isPending) return <InlineLoader />

  if (item.isError) {
    const notFound = item.error instanceof ApiError && item.error.isNotFound

    return <ErrorState title={notFound ? 'Article introuvable' : undefined} message={notFound ? 'Cet article n’existe pas dans le stock de votre entreprise.' : errorMessage(item.error)} />
  }

  const detail = item.data

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={detail.articleCode}
        subtitle={detail.description ?? undefined}
        status={<StatusBadge code={detail.status.code} label={detail.status.label} color={detail.status.color} />}
        actions={
          <>
            {detail.actions.canRequestChange && (
              <Button variant="outline" onClick={() => setAdjusting(true)}>
                <MessageSquareWarning className="size-4" aria-hidden /> Demander une correction
              </Button>
            )}
            {detail.actions.canEditMetadata && (
              <Button variant="outline" onClick={() => setEditing(true)}>
                <Pencil className="size-4" aria-hidden /> Modifier
              </Button>
            )}
          </>
        }
      />

      <StockTotalsCards totals={detail.stock} />

      <SectionCard title="Article">
        <dl className="grid gap-x-6 sm:grid-cols-3">
          <DetailField label="Code-barres">{detail.barcode}</DetailField>
          <DetailField label="Article du catalogue">{detail.catalogItem ? `${detail.catalogItem.articleCode} — ${detail.catalogItem.name}` : null}</DetailField>
          <DetailField label="Description">{detail.description}</DetailField>
        </dl>
      </SectionCard>

      <StockBalancesSection itemId={detail.id} />

      {detail.actions.canViewMovements && (
        <SectionCard title="Mouvements">
          <StockMovementsTable filters={{ stockItemId: detail.id, page: movementsPage, perPage: 10 }} onPageChange={setMovementsPage} />
        </SectionCard>
      )}

      {detail.actions.canViewReservations && (
        <SectionCard title="Réservations et commandes liées">
          <StockReservationsTable filters={{ stockItemId: detail.id, page: reservationsPage, perPage: 10 }} onPageChange={setReservationsPage} />
        </SectionCard>
      )}

      <StockItemDialog item={detail} open={editing} onOpenChange={setEditing} />
      {detail.actions.canRequestChange && <AdjustmentRequestDialog item={detail} open={adjusting} onOpenChange={setAdjusting} />}
    </div>
  )
}
