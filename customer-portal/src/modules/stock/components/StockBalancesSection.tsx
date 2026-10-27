import { MapPin } from 'lucide-react'

import { errorMessage } from '@/shared/api/errors'
import { EmptyState, ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table'

import { useStockBalances } from '../hooks/useStock'
import { formatQuantity } from '../utils/quantity'
import { locationLabel } from './stockColumns'

/**
 * Répartition d'un article par emplacement (§11, §15) — lecture seule. Le
 * client voit où est **sa** marchandise, jamais ce que l'emplacement contient
 * d'autre.
 */
export function StockBalancesSection({ itemId }: { itemId: string }) {
  const balances = useStockBalances(itemId)

  return (
    <SectionCard title="Répartition par emplacement">
      {balances.isPending ? (
        <InlineLoader />
      ) : balances.isError ? (
        <ErrorState message={errorMessage(balances.error)} onRetry={() => void balances.refetch()} />
      ) : balances.data.length === 0 ? (
        <EmptyState title="Aucun stock pour cet article" icon={MapPin}>
          Le stock apparaîtra à la première entrée enregistrée par votre transporteur.
        </EmptyState>
      ) : (
        <div className="overflow-x-auto">
          <Table aria-label="Répartition par emplacement">
            <TableHeader>
              <TableRow>
                <TableHead>Emplacement</TableHead>
                <TableHead className="text-right">Quantité</TableHead>
                <TableHead className="text-right">Réservée</TableHead>
                <TableHead className="text-right">Disponible</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {balances.data.map((balance) => (
                <TableRow key={balance.id}>
                  <TableCell>{locationLabel(balance.location)}</TableCell>
                  <TableCell className="text-right tabular-nums">{formatQuantity(balance.quantity)}</TableCell>
                  <TableCell className="text-right tabular-nums">{formatQuantity(balance.reservedQuantity)}</TableCell>
                  <TableCell className="text-right tabular-nums">{formatQuantity(balance.availableQuantity)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
    </SectionCard>
  )
}
