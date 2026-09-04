import type { TourLeg } from '../types/tour'

/**
 * Les trajets d'une tournée : comment les ranger, comment les écrire.
 *
 * Les deux grandeurs viennent brutes du calculateur d'itinéraire — des mètres
 * et des minutes. Une colonne large de dix-huit rem ne peut pas les afficher
 * telles quelles : « 8,4 km » et « 1 h 35 » se comparent plus vite que
 * « 8437 m » et « 95 min ».
 */

/** Mètres en kilomètres, une décimale : « 8,4 km ». */
export function formatDistance(meters: number): string {
  return `${(meters / 1000).toLocaleString('fr-FR', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  })} km`
}

/**
 * Minutes en durée lisible : « 45 min », « 1 h 35 », « 2 h ».
 *
 * L'heure pleine ne s'écrit pas « 2 h 00 » : le zéro se lit comme une précision
 * qu'on n'a pas, alors que la valeur est ronde.
 */
export function formatTravelTime(minutes: number): string {
  if (minutes < 60) return `${minutes} min`

  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60

  return rest === 0 ? `${hours} h` : `${hours} h ${String(rest).padStart(2, '0')}`
}

/** Ce qu'un camion a parcouru en arrivant quelque part. */
export interface Cumulative {
  minutes: number
  meters: number
}

/**
 * Les trajets indexés sur l'arrêt qu'ils desservent, cumul compris.
 *
 * Un trajet porte l'arrêt vers lequel il mène, jamais celui d'où il part :
 * c'est ce qui permet de l'afficher juste avant le bon arrêt même quand une
 * composition en cours en masque certains.
 *
 * Le serveur les rend dans l'ordre de la tournée ; c'est cet ordre qui fait le
 * cumul, pas l'ordre d'affichage. Un trajet sans arrêt d'arrivée — la donnée
 * l'autorise — compte dans le cumul mais ne s'affiche nulle part : on ne
 * saurait pas où le poser, et l'écarter du total fausserait les suivants.
 */
export function legsByStop(legs: TourLeg[]): Map<string, { leg: TourLeg; cumulative: Cumulative }> {
  const indexed = new Map<string, { leg: TourLeg; cumulative: Cumulative }>()
  const running: Cumulative = { minutes: 0, meters: 0 }

  for (const leg of legs) {
    running.minutes += leg.travelMinutes
    running.meters += leg.distanceMeters

    if (leg.tourStopId === null) continue

    indexed.set(leg.tourStopId, { leg, cumulative: { ...running } })
  }

  return indexed
}
