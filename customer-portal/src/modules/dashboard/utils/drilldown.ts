import { OTHER_KEY } from '../components/charts/chartPalette'
import type { DashboardPeriod, DashboardWidget } from '../types/dashboard'

/** Ce qu'on vient de cliquer : une série, un jour, ou les deux. */
export interface DrilldownTarget {
  /** Code de la série — un statut, une provenance. */
  code?: string | null
  /** Jour, au format ISO court. */
  day?: string | null
  /**
   * Période réglée en tête de tableau de bord, quand il y en a une.
   *
   * Elle part **avec** le clic, faute de quoi une répartition lue sur août
   * ouvrirait les commandes de tous les temps : la liste montrerait alors autre
   * chose que la part qu'on vient de cliquer, en plus grand, et rien ne le
   * signalerait. Un jour visé l'emporte sur elle — c'est plus précis, et c'est
   * ce que le geste désigne.
   */
  period?: DashboardPeriod | null
}

/**
 * L'adresse de la liste qui montre **ce qu'on vient de cliquer**.
 *
 * C'était le défaut de la carte cliquable : viser la colonne du 3 septembre et
 * arriver sur les commandes de tous les temps. La carte tenait sa promesse à la
 * lettre — elle mène aux commandes — mais pas celle que le geste avait faite.
 *
 * Les noms des paramètres viennent du **serveur**, avec la route. Les écrire
 * ici aurait donné deux vérités sur le contrat de la liste, et la seconde
 * n'aurait rien signalé en dérivant : un paramètre inconnu est ignoré, la liste
 * s'ouvre sans filtre, et l'on retombe exactement sur le défaut qu'on corrige.
 *
 * **`null` plutôt qu'un lien vers la liste entière** quand rien ne peut être
 * transmis — une part « Autres » qui recouvre plusieurs codes, un widget sans
 * descripteur. Une part qui mène ailleurs que là où elle promet vaut moins
 * qu'une part qui ne bouge pas.
 */
export function drilldownTo(widget: DashboardWidget, target: DrilldownTarget): string | null {
  const { route, drilldown } = widget

  if (route === null || drilldown === null) {
    return null
  }

  const params = new URLSearchParams()

  // « Autres » est un repli d'affichage, jamais une valeur de colonne :
  // l'envoyer comme statut rendrait une liste vide sous une part qui n'est pas
  // vide.
  if (drilldown.seriesParam !== null && target.code != null && target.code !== OTHER_KEY) {
    params.set(drilldown.seriesParam, target.code)
  }

  // Un jour se transmet en deux bornes identiques : la liste filtre sur un
  // intervalle, et « du 3 au 3 » est la seule façon de lui demander une
  // journée.
  const from = target.day ?? target.period?.from ?? null
  const to = target.day ?? target.period?.to ?? null

  if (drilldown.fromParam !== null && from !== null) params.set(drilldown.fromParam, from)
  if (drilldown.toParam !== null && to !== null) params.set(drilldown.toParam, to)

  const query = params.toString()

  return query === '' ? null : `${route}?${query}`
}
