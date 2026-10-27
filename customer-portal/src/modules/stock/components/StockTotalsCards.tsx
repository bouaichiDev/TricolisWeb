import { Card, CardContent } from '@/shared/components/ui/card'

import type { StockTotals } from '../types'
import { formatQuantity } from '../utils/quantity'

interface StockTotalsCardsProps {
  totals: StockTotals
  /** Nombre d'articles, pour le résumé global. */
  itemsCount?: number
}

/**
 * Quantité totale, réservée, disponible — et le nombre d'articles au résumé.
 * Les chiffres viennent du backend, calculés en SQL ; rien n'est additionné ici.
 */
export function StockTotalsCards({ totals, itemsCount }: StockTotalsCardsProps) {
  const cards = [
    ...(itemsCount === undefined ? [] : [{ label: 'Articles', value: String(itemsCount) }]),
    { label: 'Quantité totale', value: formatQuantity(totals.quantity) },
    { label: 'Réservée', value: formatQuantity(totals.reservedQuantity) },
    { label: 'Disponible', value: formatQuantity(totals.availableQuantity) },
  ]

  return (
    <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Quantités">
      {cards.map((card) => (
        <Card key={card.label}>
          <CardContent className="py-4">
            <dt className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{card.label}</dt>
            <dd className="mt-1 text-2xl font-semibold tabular-nums">{card.value}</dd>
          </CardContent>
        </Card>
      ))}
    </dl>
  )
}
