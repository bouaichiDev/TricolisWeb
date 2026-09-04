import type { ChartSeries } from '../../types/dashboard'

/**
 * Huit teintes, dans cet ordre, et jamais une neuvième.
 *
 * Les valeurs vivent dans `index.css` — elles y changent avec le thème sans que
 * ce fichier le sache. Ce qu'il tient, lui, c'est **l'attribution** : quelle
 * série reçoit quelle teinte.
 *
 * L'ordre n'est pas décoratif. C'est ce qui garantit que deux séries voisines
 * restent distinctes, y compris pour un lecteur daltonien ; il a été retenu
 * parce qu'il passe cette mesure, pas parce qu'il est joli. En inventer une
 * neuvième — générée, ou en recyclant la première — donnerait une couleur
 * indistinguable d'une autre sous vision altérée : la queue se replie donc sur
 * « Autres », en gris.
 */
export const CHART_SLOTS = 8

const SLOT_VARIABLES = Array.from({ length: CHART_SLOTS }, (_, index) => `var(--chart-${index + 1})`)

export const OTHER_KEY = '__other__'

export interface ChartSlice {
  code: string
  value: number
  /** Part du total, entre 0 et 1. Toujours 0 quand le total est nul. */
  share: number
  color: string
  isOther: boolean
}

/**
 * Répartit les séries en tranches colorées.
 *
 * La couleur suit **la série, jamais son rang** : les teintes sont attribuées
 * dans l'ordre où le serveur rend les séries — qui est celui du référentiel des
 * statuts, donc celui du cycle de vie — et non par valeur décroissante. Sans
 * cela, une commande de plus repeindrait la moitié du graphe, et un lecteur qui
 * avait retenu « les brouillons sont bleus » se tromperait le lendemain.
 *
 * Au-delà de huit séries, les suivantes fusionnent en « Autres ». Le seuil
 * n'est pas arbitraire : c'est le nombre de teintes qu'on sait tenir
 * distinctes.
 */
export function toSlices(series: ChartSeries[]): ChartSlice[] {
  const total = series.reduce((sum, entry) => sum + entry.value, 0)
  const share = (value: number) => (total === 0 ? 0 : value / total)

  const named = series.slice(0, CHART_SLOTS).map((entry, index) => ({
    code: entry.code,
    value: entry.value,
    share: share(entry.value),
    color: SLOT_VARIABLES[index],
    isOther: false,
  }))

  const tail = series.slice(CHART_SLOTS)

  if (tail.length === 0) return named

  const tailValue = tail.reduce((sum, entry) => sum + entry.value, 0)

  return [
    ...named,
    {
      code: OTHER_KEY,
      value: tailValue,
      share: share(tailValue),
      color: 'var(--chart-other)',
      isOther: true,
    },
  ]
}

export function totalOf(series: ChartSeries[]): number {
  return series.reduce((sum, entry) => sum + entry.value, 0)
}

/**
 * Remet les séries dans l'ordre du **cycle de vie**, et non dans celui du code.
 *
 * Le serveur les rend triées par code, ce qui est stable mais dépourvu de sens :
 * `completed, confirmed, draft` place la fin avant le début, et le lecteur
 * remonte le pipeline à l'envers. Le référentiel des statuts, lui, porte un
 * rang — c'est celui qu'un administrateur règle pour les afficher partout
 * ailleurs.
 *
 * Trier ici, et non par valeur décroissante, préserve la règle qui compte : la
 * **couleur suit la série, jamais son rang**. Une commande de plus ne change ni
 * la place ni la teinte de quoi que ce soit ; un tri par valeur aurait repeint
 * la moitié du graphe au premier enregistrement.
 *
 * Un code absent du référentiel — les statuts laissés en chaîne libre — passe
 * après ceux qu'il connaît, dans l'ordre où le serveur les a rendus.
 */
export function orderByLifecycle(series: ChartSeries[], referential: string[]): ChartSeries[] {
  if (referential.length === 0) return series

  const rankOf = (code: string) => {
    const rank = referential.indexOf(code)

    return rank === -1 ? referential.length : rank
  }

  return [...series].sort((a, b) => rankOf(a.code) - rankOf(b.code))
}
