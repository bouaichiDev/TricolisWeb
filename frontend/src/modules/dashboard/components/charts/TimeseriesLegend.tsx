import { Link } from 'react-router-dom'

import type { ChartSlice } from './chartPalette'

interface TimeseriesLegendProps {
  slices: ChartSlice[]
  /** Valeurs du jour survolé, alignées sur `slices` ; vide hors survol. */
  values: number[]
  labelOf: (slice: ChartSlice) => string
  /** Adresse de la liste filtrée sur cette série, quand elle existe. */
  linkOf?: (slice: ChartSlice) => string | null
}

/**
 * La légende d'un graphe temporel, qui devient l'infobulle au survol.
 *
 * Une infobulle flottante aurait recouvert la donnée qu'on vient de viser, et
 * demandé de la placer sans jamais sortir de la carte — deux problèmes pour un
 * gain nul, puisque la légende occupe déjà une ligne sous le graphe et ne bouge
 * pas. Elle affiche donc les valeurs du jour survolé **à la place de rien**, et
 * l'œil n'a pas à quitter la zone où il travaille.
 *
 * Hors survol, elle reste ce qu'elle doit être : la table des couleurs. Elle est
 * **toujours présente** dès deux séries — sans elle, l'identité reposerait sur
 * la seule couleur, ce qu'aucun lecteur daltonien ne peut suivre.
 */
export function TimeseriesLegend({ slices, values, labelOf, linkOf }: TimeseriesLegendProps) {
  return (
    <ul className="flex flex-wrap items-center gap-x-4 gap-y-1">
      {slices.map((slice, index) => {
        const row = (
          <>
            <span
              aria-hidden
              className="size-2.5 shrink-0 rounded-full"
              style={{ backgroundColor: slice.color }}
            />
            <span className="text-muted-foreground">{labelOf(slice)}</span>
            {values[index] === undefined ? null : (
              <span className="font-medium tabular-nums">{values[index]}</span>
            )}
          </>
        )

        const to = linkOf?.(slice) ?? null

        return (
          <li key={slice.code} className="flex items-center gap-1.5 text-sm">
            {to === null ? (
              row
            ) : (
              <Link to={to} className="flex items-center gap-1.5 rounded-md hover:text-foreground">
                {row}
              </Link>
            )}
          </li>
        )
      })}
    </ul>
  )
}
