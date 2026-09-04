import type { ChartSlice } from './chartPalette'

interface ColumnStackProps {
  /** Une entrée par série, dans l'ordre d'empilement, avec sa couleur. */
  slices: ChartSlice[]
  values: number[][]
  ceiling: number
  hovered: number | null
}

/**
 * Les colonnes empilées, une par jour.
 *
 * **Fines, et jamais collées au bord de leur intervalle.** Une colonne qui
 * remplit toute sa case donne un mur de couleur ; l'air autour d'elle est ce
 * qui la rend lisible. D'où `w-[58%]`, centré : le reste de l'intervalle est du
 * vide, et c'est délibéré.
 *
 * Les 2 px entre les segments d'une même pile sont du **fond**, jamais un
 * trait : une bordure ajouterait de l'encre qui n'est pas de la donnée. Le
 * sommet de la pile est arrondi, la base est carrée — l'arrondi marque la fin
 * de la donnée, et l'appliquer en bas ferait flotter la colonne au-dessus de sa
 * ligne de zéro.
 *
 * Un jour sans rien ne dessine rien. Une colonne de un pixel à zéro se lirait
 * comme une valeur minuscule, quand il n'y en a aucune.
 */
export function ColumnStack({ slices, values, ceiling, hovered }: ColumnStackProps) {
  return (
    <div className="absolute inset-0 flex items-end">
      {values.map((day, index) => {
        const total = day.reduce((sum, value) => sum + value, 0)

        return (
          <div key={index} className="flex h-full flex-1 items-end justify-center">
            <div
              className="flex w-[58%] flex-col-reverse gap-0.5 overflow-hidden rounded-t-[3px] transition-opacity"
              style={{
                height: `${(total / ceiling) * 100}%`,
                opacity: hovered === null || hovered === index ? 1 : 0.4,
              }}
            >
              {day.map((value, serie) =>
                value === 0 ? null : (
                  <div
                    key={slices[serie]?.code ?? serie}
                    style={{
                      backgroundColor: slices[serie]?.color,
                      flexGrow: value,
                      flexBasis: 0,
                    }}
                  />
                ),
              )}
            </div>
          </div>
        )
      })}
    </div>
  )
}
