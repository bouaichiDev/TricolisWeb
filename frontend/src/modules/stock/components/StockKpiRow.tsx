import { useTranslation } from 'react-i18next'

import { Skeleton } from '@/shared/components/ui/skeleton'

import type { StockBalance } from '../types/stock'
import { formatStockQuantity, sumQuantities } from '../utils/stockSources'

interface StockKpiRowProps {
  balances: StockBalance[]
  isLoading: boolean
  /** Vrai quand la pagination cache des lignes : les chiffres sont partiels. */
  isPartial: boolean
  total: number
}

/**
 * Indicateurs de stock, calculés sur les lignes chargées.
 *
 * Aucune route ne renvoie de total : les soldes arrivent paginés, et l'addition
 * se fait donc ici, sur ce que la page contient. C'est exact tant que tout tient
 * sur une page, et **l'écran dit quand ce n'est plus le cas** plutôt que
 * d'afficher un total silencieusement faux.
 *
 * Les quantités arrivent en chaînes décimales : `sumQuantities` les convertit
 * une seule fois, pour l'addition. Aucune de ces valeurs ne repart au serveur.
 */
export function StockKpiRow({ balances, isLoading, isPartial, total }: StockKpiRowProps) {
  const { t } = useTranslation()

  if (isLoading) {
    return (
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {[0, 1, 2, 3].map((key) => (
          <Skeleton key={key} className="h-24 rounded-lg" />
        ))}
      </div>
    )
  }

  const quantity = sumQuantities(balances.map((balance) => balance.quantity))
  const reserved = sumQuantities(balances.map((balance) => balance.reservedQuantity))
  const available = sumQuantities(balances.map((balance) => balance.availableQuantity))
  const locations = new Set(balances.map((balance) => balance.stockLocationId)).size

  const cards = [
    { key: 'quantity', label: t('stock.kpi.quantity'), value: formatStockQuantity(quantity) },
    { key: 'reserved', label: t('stock.kpi.reserved'), value: formatStockQuantity(reserved) },
    { key: 'available', label: t('stock.kpi.available'), value: formatStockQuantity(available) },
    { key: 'locations', label: t('stock.kpi.locations'), value: String(locations) },
  ]

  return (
    <div className="flex flex-col gap-2">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {cards.map((card) => (
          <div key={card.key} className="rounded-lg border bg-card p-4">
            <p className="text-sm text-muted-foreground">{card.label}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">{card.value}</p>
          </div>
        ))}
      </div>

      <p className="text-xs text-muted-foreground">
        {isPartial
          ? t('stock.kpi.partial', { shown: balances.length, total })
          : t('stock.kpi.complete', { count: balances.length })}
      </p>
    </div>
  )
}
