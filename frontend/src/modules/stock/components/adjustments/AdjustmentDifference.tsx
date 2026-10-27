import { cn } from '@/shared/utils/cn'

import { isNegativeQuantity, isZeroQuantity } from '../../types/stockAdjustment'
import { formatStockQuantity } from '../../utils/stockSources'

/** Un écart signé, tel que le serveur l'a calculé : `+5`, `−2,5`, `0`. */
export function AdjustmentDifference({ value }: { value: string }) {
  const negative = isNegativeQuantity(value)
  const zero = isZeroQuantity(value)
  const amount = formatStockQuantity(negative ? value.trim().slice(1) : value)

  return (
    <span
      className={cn(
        'font-medium tabular-nums',
        zero ? 'text-muted-foreground' : negative ? 'text-destructive' : 'text-emerald-600',
      )}
    >
      {zero ? amount : `${negative ? '−' : '+'}${amount}`}
    </span>
  )
}
