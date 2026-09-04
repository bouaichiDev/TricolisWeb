import type { ChartSlice } from './chartPalette'

interface DonutChartProps {
  slices: ChartSlice[]
  total: number
  hovered: string | null
  onHover: (code: string | null) => void
  labelOf: (slice: ChartSlice) => string
}

/** Rayon et épaisseur, dans un carré de 100 unités de côté. */
const RADIUS = 42
const THICKNESS = 14
const CIRCUMFERENCE = 2 * Math.PI * RADIUS

/**
 * Un camembert **évidé**, et non plein.
 *
 * Le trou n'est pas une mode : un disque plein demande de comparer des angles au
 * sommet, ce que l'œil fait mal ; un anneau les rend en longueurs d'arc, qu'il
 * lit bien mieux. Et le centre libéré porte le total, qui n'a nulle part
 * ailleurs où aller sans occuper une ligne de plus.
 *
 * **Il n'est pas dessiné avec des `path` calculés**, mais avec un seul cercle
 * par part et un `stroke-dasharray`. Une part est donc un trait long de son
 * arc, décalé d'autant que les précédentes : pas de trigonométrie, pas de
 * commandes SVG assemblées à la main, et une géométrie qu'on relit.
 *
 * Les 2 px de fond entre les parts sont **retirés à l'arc**, pas ajoutés
 * autour : les ajouter aurait fait un tour de plus de 360°, et la dernière part
 * aurait mordu sur la première.
 *
 * `-90°` de rotation pour partir à midi. Sans elle, la première part commence à
 * trois heures, là où personne ne commence à lire un cadran.
 */
export function DonutChart({ slices, total, hovered, onHover, labelOf }: DonutChartProps) {
  let consumed = 0

  return (
    <div className="relative mx-auto aspect-square w-full max-w-[180px]">
      <svg viewBox="0 0 100 100" className="size-full -rotate-90" role="presentation">
        {/* La piste, sous les parts : sans elle, un camembert presque vide
            n'aurait pas de forme, et l'on ne saurait pas qu'il en manque. */}
        <circle
          cx="50"
          cy="50"
          r={RADIUS}
          fill="none"
          strokeWidth={THICKNESS}
          className="stroke-muted"
        />

        {slices.map((slice) => {
          const length = total === 0 ? 0 : (slice.value / total) * CIRCUMFERENCE
          const offset = consumed
          consumed += length

          return (
            <circle
              key={slice.code}
              cx="50"
              cy="50"
              r={RADIUS}
              fill="none"
              stroke={slice.color}
              strokeWidth={THICKNESS}
              strokeDasharray={`${Math.max(length - 2, 0)} ${CIRCUMFERENCE}`}
              strokeDashoffset={-offset}
              opacity={hovered === null || hovered === slice.code ? 1 : 0.3}
              className="transition-opacity"
              onMouseEnter={() => onHover(slice.code)}
              onMouseLeave={() => onHover(null)}
            >
              <title>{`${labelOf(slice)} — ${slice.value}`}</title>
            </circle>
          )
        })}
      </svg>

      {/* Le total, au centre. Il n'est pas décoratif : deux répartitions
          identiques peuvent porter sur dix commandes ou sur mille, et l'anneau
          seul ne le dit pas. */}
      <span className="pointer-events-none absolute inset-0 flex items-center justify-center text-2xl font-semibold">
        {total}
      </span>
    </div>
  )
}
