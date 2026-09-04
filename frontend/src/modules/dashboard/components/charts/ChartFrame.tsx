import type { ReactNode } from 'react'

import { formatDay, tickIndexes } from './timeScale'

interface ChartFrameProps {
  buckets: string[]
  ceiling: number
  /** Valeurs des graduations, de zéro au plafond. */
  ticks: number[]
  language: string
  /** Index du jour survolé, pour le trait de visée. */
  hovered: number | null
  onHover: (index: number | null) => void
  /**
   * Abscisse d'un jour, en pourcentage de la largeur.
   *
   * Les deux graphes ne placent pas leurs jours au même endroit, et la visée
   * doit tomber sur la donnée : une colonne occupe le **milieu de son
   * intervalle**, un point de courbe est **sur le bord** — le premier à 0 %, le
   * dernier à 100 %. Une seule convention aurait décalé l'un des deux d'une
   * demi-case, ce qui se voit surtout là où on regarde.
   */
  xOf: (index: number) => number
  children: ReactNode
}

/**
 * Le cadre commun aux deux graphes temporels : graduations, repères, visée.
 *
 * **La hauteur inclut la bande des dates.** C'est l'erreur classique d'une carte
 * de tableau de bord : on fixe la hauteur du tracé, les libellés de l'axe
 * débordent, et le navigateur pose un minuscule ascenseur vertical à
 * l'intérieur de la carte. Ici le tracé a sa hauteur, la bande a la sienne, et
 * la carte grandit de la somme.
 *
 * Les lignes de grille sont des **filets pleins**, d'un ton au-dessus du fond.
 * Pointillées, elles se liraient comme un seuil ou une projection ; épaisses,
 * elles concurrenceraient la donnée. Elles sont là pour qu'on retrouve une
 * hauteur, pas pour se faire voir.
 *
 * Le survol est capté par **une bande invisible par jour**, large de tout
 * l'intervalle et haute de tout le graphe. Viser une colonne de six pixels ou
 * un point de courbe demanderait une précision que personne n'a ; la bande, elle,
 * fait quarante pixels de large.
 */
export function ChartFrame({
  buckets,
  ceiling,
  ticks: scale,
  language,
  hovered,
  onHover,
  xOf,
  children,
}: ChartFrameProps) {
  const dayTicks = tickIndexes(buckets.length)

  return (
    <div className="flex w-full gap-2">
      {/* Les graduations, hors du tracé : dans le SVG, elles se seraient
          étirées avec lui et le texte aurait grossi avec la carte.

          Positionnées à la même hauteur que leur ligne, et non réparties par
          `justify-between` : réparti, un libellé s'aligne par son bord haut et
          se décale d'une demi-hauteur de texte, ce qui se voit exactement là où
          l'on cherche à lire une valeur. */}
      <div className="relative h-40 w-8 shrink-0 text-right text-[11px] tabular-nums text-muted-foreground">
        {scale.map((value) => (
          <span
            key={value}
            className="absolute right-0 translate-y-1/2 leading-none"
            style={{ bottom: `${(value / ceiling) * 100}%` }}
          >
            {value}
          </span>
        ))}
      </div>

      <div className="min-w-0 flex-1">
        <div className="relative h-40 w-full" onMouseLeave={() => onHover(null)}>
          {scale.map((value) => (
            <span
              key={value}
              aria-hidden
              className="absolute inset-x-0 h-px bg-border"
              style={{ bottom: `${(value / ceiling) * 100}%` }}
            />
          ))}

          {children}

          {/* Le trait de visée, sous le curseur. Il relie le point survolé à sa
              date, ce qu'un simple surlignage de la colonne ne fait pas. */}
          {hovered !== null ? (
            <span
              aria-hidden
              className="absolute inset-y-0 w-px bg-foreground/20"
              style={{ left: `${xOf(hovered)}%` }}
            />
          ) : null}

          <div className="absolute inset-0 flex">
            {buckets.map((bucket, index) => (
              <button
                key={bucket}
                type="button"
                tabIndex={-1}
                aria-label={bucket}
                className="h-full flex-1 cursor-default"
                onMouseEnter={() => onHover(index)}
              />
            ))}
          </div>
        </div>

        <div className="relative mt-2 h-4 text-[11px] text-muted-foreground">
          {/* Les dates extrêmes sont alignées sur le bord plutôt que centrées :
              centrée, la première déborderait à gauche du cadre et se ferait
              couper. */}
          {dayTicks.map((index) => {
            const left = xOf(index)

            return (
              <span
                key={index}
                className="absolute whitespace-nowrap"
                style={{
                  left: `${left}%`,
                  transform:
                    left <= 1 ? 'none' : left >= 99 ? 'translateX(-100%)' : 'translateX(-50%)',
                }}
              >
                {formatDay(buckets[index], language)}
              </span>
            )
          })}
        </div>
      </div>
    </div>
  )
}
