import type { ChartSlice } from './chartPalette'

interface LinePlotProps {
  slices: ChartSlice[]
  values: number[][]
  ceiling: number
  hovered: number | null
}

/**
 * Les courbes, tracées en pourcentages du cadre.
 *
 * `preserveAspectRatio="none"` fait travailler le SVG en coordonnées relatives :
 * le tracé s'étire avec la carte sans qu'on ait à mesurer quoi que ce soit en
 * pixels. La contrepartie est que tout s'étire — d'où `non-scaling-stroke`, qui
 * garde les 2 px du trait quelle que soit la largeur.
 *
 * **Les points de survol sont hors du SVG**, en HTML positionné en pourcentages.
 * Dessinés dedans, ils auraient subi le même étirement : un cercle serait devenu
 * une ellipse d'autant plus aplatie que la carte est large, ce qui se voit
 * immédiatement et ne se rattrape pas au rayon.
 *
 * **Des segments droits, pas des courbes lissées.** Une interpolation douce
 * invente des valeurs entre deux jours — un creux qui n'a pas eu lieu, un pic
 * qui dépasse le maximum réel — et la ligne cesse de dire ce qui s'est passé.
 *
 * Un point est marqué **au jour survolé seulement**. Un marqueur sur chacun des
 * trente jours ferait trente disques sur trois courbes : la donnée disparaîtrait
 * sous ses propres repères.
 */
export function LinePlot({ slices, values, ceiling, hovered }: LinePlotProps) {
  const count = values[0]?.length ?? 0

  const x = (index: number) => (count <= 1 ? 50 : (index / (count - 1)) * 100)
  const y = (value: number) => 100 - (value / ceiling) * 100

  return (
    <>
      <svg
        viewBox="0 0 100 100"
        preserveAspectRatio="none"
        className="absolute inset-0 size-full"
        role="presentation"
      >
        {values.map((serie, index) => (
          <polyline
            key={slices[index]?.code ?? index}
            points={serie.map((value, day) => `${x(day)},${y(value)}`).join(' ')}
            fill="none"
            stroke={slices[index]?.color}
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            vectorEffect="non-scaling-stroke"
          />
        ))}
      </svg>

      {hovered !== null
        ? values.map((serie, index) => (
            <span
              key={slices[index]?.code ?? index}
              aria-hidden
              // L'anneau de fond détache le point de la courbe qu'il croise, et
              // des autres points quand deux séries se rejoignent.
              className="pointer-events-none absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-card"
              style={{
                backgroundColor: slices[index]?.color,
                left: `${x(hovered)}%`,
                top: `${y(serie[hovered] ?? 0)}%`,
              }}
            />
          ))
        : null}
    </>
  )
}
