import type { ChartSlice } from './chartPalette'
import { cn } from '@/shared/utils/cn'

interface ChartLegendProps {
  slices: ChartSlice[]
  hovered: string | null
  onHover: (code: string | null) => void
  labelOf: (slice: ChartSlice) => string
  shareFormatter: Intl.NumberFormat
}

/**
 * La légende, qui est aussi le tableau.
 *
 * Deux exigences se rejoignent ici, et une seule liste les tient. La **légende**
 * est obligatoire dès deux séries : sans elle, l'identité reposerait sur la
 * seule couleur, ce qu'aucun lecteur daltonien ne peut suivre. Le **tableau**
 * est ce qui rend chaque valeur lisible sans survoler quoi que ce soit — un
 * chiffre qu'on ne peut atteindre qu'à la souris n'est pas accessible.
 *
 * Les mêmes lignes font les deux : une pastille de couleur, un nom, une valeur,
 * une part. La pastille est **à côté** du texte et non dedans — trois des huit
 * teintes n'ont pas le contraste d'un texte sur fond blanc, et colorer le
 * libellé les rendrait illisibles. Le texte porte des jetons de texte, la
 * couleur reste sur la marque.
 *
 * Le survol relie les deux moitiés : passer sur une ligne éclaircit son segment
 * dans la barre, et réciproquement.
 */
export function ChartLegend({
  slices,
  hovered,
  onHover,
  labelOf,
  shareFormatter,
}: ChartLegendProps) {
  return (
    <ul className="flex flex-col" onMouseLeave={() => onHover(null)}>
      {slices.map((slice) => (
        <li
          key={slice.code}
          onMouseEnter={() => onHover(slice.code)}
          className={cn(
            'flex items-center gap-2.5 rounded-md px-1.5 py-1 transition-colors',
            hovered === slice.code && 'bg-accent/60',
          )}
        >
          <span
            aria-hidden
            className="size-2.5 shrink-0 rounded-full"
            style={{ backgroundColor: slice.color }}
          />
          <span className="min-w-0 flex-1 truncate text-sm">{labelOf(slice)}</span>
          <span className="text-sm font-medium tabular-nums">{slice.value}</span>
          <span className="w-11 shrink-0 text-right text-xs tabular-nums text-muted-foreground">
            {shareFormatter.format(slice.share)}
          </span>
        </li>
      ))}
    </ul>
  )
}
