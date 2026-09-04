import type { ChartSlice } from './chartPalette'

interface CompositionBarProps {
  slices: ChartSlice[]
  /** Tranche survolée, mise en avant ; les autres s'effacent. */
  hovered: string | null
  onHover: (code: string | null) => void
  labelOf: (slice: ChartSlice) => string
}

/**
 * Une barre, et la composition d'un tout.
 *
 * **Une seule barre segmentée, et non une barre par catégorie.** La question à
 * laquelle ce graphe répond est « comment mes commandes se répartissent », pas
 * « laquelle est la plus longue » : les parts s'additionnent, et les voir
 * s'additionner est l'information. Des barres séparées auraient demandé de les
 * additionner de tête pour retrouver le tout.
 *
 * Trois détails font tout le rendu, et aucun n'est cosmétique :
 *
 * - **2 px de fond entre les segments**, jamais un trait. Une bordure ajoute de
 *   l'encre qui n'est pas de la donnée ; le vide sépare aussi bien et ne pèse
 *   rien.
 * - **les extrémités arrondies, l'intérieur carré.** L'arrondi marque la fin de
 *   la donnée ; l'appliquer à chaque segment le ferait mentir sur les frontières
 *   internes.
 * - **10 px d'épaisseur.** Un bloc épais et saturé se lit comme un bandeau
 *   décoratif ; c'est la donnée qui doit être la seule chose bruyante.
 *
 * La couleur ne porte jamais seule l'identité : chaque segment est repris dans
 * la légende, avec son nom et sa valeur.
 */
export function CompositionBar({ slices, hovered, onHover, labelOf }: CompositionBarProps) {
  return (
    <div
      className="flex h-2.5 w-full gap-0.5 overflow-hidden rounded-full"
      onMouseLeave={() => onHover(null)}
    >
      {slices.map((slice) => (
        <div
          key={slice.code}
          // Le titre natif est le filet de sécurité, pas le moyen de lire la
          // valeur : elle figure aussi dans la légende, en toutes lettres.
          title={`${labelOf(slice)} — ${slice.value}`}
          onMouseEnter={() => onHover(slice.code)}
          style={{
            backgroundColor: slice.color,
            // `flexGrow` plutôt qu'une largeur en pourcentage : les 2 px de
            // séparation sont pris sur la barre, et des pourcentages calculés
            // sans eux déborderaient du conteneur.
            flexGrow: slice.value,
            flexBasis: 0,
            opacity: hovered === null || hovered === slice.code ? 1 : 0.35,
          }}
          className="min-w-[3px] transition-opacity"
        />
      ))}
    </div>
  )
}
