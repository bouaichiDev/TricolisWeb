import type { TimeseriesData } from '../../types/dashboard'

/**
 * L'échelle verticale, et les repères de l'axe des abscisses.
 *
 * Deux calculs que les deux graphes temporels partagent. Les écrire deux fois
 * aurait suffi à ce que les colonnes et les courbes ne s'arrêtent pas au même
 * plafond — et deux cartes côte à côte se seraient contredites.
 */

/**
 * L'échelle verticale : un plafond, et des graduations qu'on lit.
 *
 * Diviser le plafond en trois donnait « 100 / 67 / 33 / 0 » — des nombres exacts
 * que personne ne lit, parce qu'ils ne servent qu'à retrouver une hauteur. On
 * choisit donc d'abord le **pas**, pris parmi 1, 2 et 5 fois une puissance de
 * dix — les seuls dont l'œil déduit les intermédiaires sans y penser — puis le
 * plafond au multiple supérieur de ce pas.
 *
 * Le nombre de lignes varie donc de trois à cinq, et c'est le prix à payer : un
 * nombre de lignes fixe impose des graduations arbitraires, ce qui est le
 * défaut le plus visible des deux.
 *
 * Le pas ne descend jamais sous 1 : ces graphes comptent des commandes et des
 * envois, et une graduation à 0,5 promettrait des demi-commandes.
 *
 * Zéro partout rend un plafond de 1 : une échelle qui va de zéro à zéro n'a pas
 * de hauteur, et toute division par elle serait infinie.
 */
export interface Scale {
  ceiling: number
  /** Valeurs des graduations, de zéro au plafond. */
  ticks: number[]
}

export function niceScale(highest: number, intervals = 4): Scale {
  if (highest <= 0) return { ceiling: 1, ticks: [0, 1] }

  const rough = highest / intervals
  const magnitude = 10 ** Math.floor(Math.log10(rough))
  const normalized = rough / magnitude

  const step = Math.max(
    1,
    (normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10) * magnitude,
  )

  const ceiling = Math.ceil(highest / step) * step
  const ticks: number[] = []

  for (let value = 0; value <= ceiling; value += step) {
    ticks.push(value)
  }

  return { ceiling, ticks }
}

/**
 * Le plus haut point, colonnes **empilées** comprises.
 *
 * Pour des colonnes, c'est la somme des séries d'un même jour qui touche le
 * plafond, pas la plus haute d'entre elles. Prendre le maximum série par série
 * ferait déborder chaque pile du cadre.
 */
export function highestOf(data: TimeseriesData, stacked: boolean): number {
  const count = data.buckets.length

  if (count === 0 || data.series.length === 0) return 0

  if (!stacked) {
    return Math.max(...data.series.flatMap((serie) => serie.values))
  }

  let highest = 0

  for (let index = 0; index < count; index++) {
    const sum = data.series.reduce((total, serie) => total + (serie.values[index] ?? 0), 0)
    highest = Math.max(highest, sum)
  }

  return highest
}

/**
 * Les jours qu'on **écrit** sous l'axe.
 *
 * Jamais tous : trente dates sous un graphe se chevauchent, et le lecteur ne
 * lit alors plus aucune. On en garde au plus cinq, réparties, dont toujours la
 * première et la dernière — ce sont elles qui donnent la période couverte, et
 * c'est la première chose qu'on cherche.
 */
export function tickIndexes(count: number, wanted = 5): number[] {
  if (count <= wanted) return Array.from({ length: count }, (_, index) => index)

  const step = (count - 1) / (wanted - 1)

  return Array.from({ length: wanted }, (_, index) => Math.round(index * step))
}

/**
 * Un jour, écrit court : « 12 sept. ».
 *
 * L'année est omise — la fenêtre fait au plus trente jours, et deux dates y
 * appartiennent forcément à la même. La répéter trente fois n'apprendrait rien.
 */
export function formatDay(iso: string, language: string): string {
  const date = new Date(`${iso}T00:00:00`)

  return new Intl.DateTimeFormat(language, { day: 'numeric', month: 'short' }).format(date)
}
