/**
 * Le tableau de bord, tel que le backend le rend.
 *
 * Le frontend ne décide **pas** quels widgets existent, ni lesquels s'affichent.
 * Le catalogue vit dans `App\Shared\Dashboard\DashboardWidgetRegistry`, la
 * sélection dans la configuration de chaque rôle, et l'arbitrage — union des
 * rôles, intersection des permissions — dans `DashboardComposer`. Ce qui arrive
 * ici a déjà passé les trois filtres : un widget absent de la réponse est un
 * widget interdit, et son chiffre n'a jamais été calculé.
 *
 * D'où l'absence de tout type nommant un rôle. Écrire
 * `type DashboardRole = 'ADMIN' | 'PLANNER'` remettrait la décision côté
 * navigateur, où elle se contourne — et figerait dans le code des rôles que
 * chaque organisation crée elle-même.
 */

/**
 * Les sept formes qu'un widget peut prendre.
 *
 * Jeu fermé, et c'est ce qui rend le rendu sûr : `DashboardWidgetRenderer` fait
 * correspondre ces valeurs à sept composants écrits ici. Aucun nom de composant
 * ne voyage depuis la base.
 *
 * Trois se ressemblent et ne se remplacent pas — c'est le catalogue qui
 * tranche, sur le nombre de parts que la série peut atteindre :
 *
 * - `chart` — une barre de composition : beaucoup de parts, aux noms longs ;
 * - `donut` — un camembert : six parts au plus, lues d'un coup d'œil ;
 * - `gauge` — un seul rapport, une part contre son tout.
 */
export type DashboardWidgetType =
  | 'kpi'
  | 'chart'
  | 'donut'
  | 'gauge'
  | 'list'
  | 'alert'
  | 'quick_action'

export type DashboardWidgetSize = 'small' | 'medium' | 'large' | 'full'

export type DashboardWidgetCategory =
  | 'operations'
  | 'planning'
  | 'claims'
  | 'billing'
  | 'stock'
  | 'communications'
  | 'integrations'
  | 'administration'
  | 'quick_actions'

/** Un chiffre. `null` dit « pas de valeur », ce qui n'est pas zéro. */
export interface KpiData {
  value: number | null
  unit: string | null
}

export interface AlertData {
  value: number
}

export interface ChartSeries {
  code: string
  value: number
}

/**
 * `source` désigne l'entité au référentiel des statuts, quand il s'agit d'un
 * statut : le libellé vient alors de ce qu'un administrateur a réglé, et non
 * d'une traduction livrée qui l'ignorerait. `null` pour une série qui se nomme
 * d'elle-même — une devise, par exemple.
 *
 * `mode` dit si les valeurs **se comparent**, et le rendu en dépend
 * entièrement :
 *
 * - `share` — des parts d'un même tout, qui s'additionnent : une barre de
 *   composition les montre s'additionner ;
 * - `amounts` — des montants dans des devises différentes : aucune barre, car
 *   une longueur proportionnelle affirmerait une comparaison qui n'existe pas.
 *
 * Le serveur le déclare plutôt que de laisser le frontend le deviner : c'est
 * lui qui sait que `currency_code` sépare des monnaies incomparables.
 */
export type ChartMode = 'share' | 'amounts'

export interface ChartData {
  mode: ChartMode
  /** Entité au référentiel des statuts, dont les libellés sont réglables. */
  source: string | null
  /**
   * Espace de traduction livré — `orderSources`, `communicationChannels`.
   *
   * Exclusif avec `source` : ces codes viennent d'énumérations PHP, que
   * personne ne renomme et que le référentiel ne connaît pas. Les deux absents,
   * le code se nomme lui-même — une devise, par exemple.
   */
  labels: string | null
  series: ChartSeries[]
}

/**
 * Un rapport, et son tout.
 *
 * Le pourcentage n'est **pas** transmis : la part et le tout le sont, et
 * l'interface affiche les deux. « 72 % » ne dit pas si l'on parle de neuf cas
 * sur douze ou de neuf cents sur mille deux cents.
 *
 * Un `total` à zéro est une réponse valide — « rien à mesurer » — et non une
 * division à faire quand même.
 */
export interface GaugeData {
  value: number
  total: number
}

export interface ListItem {
  id: string
  title: string | null
  subtitle: string | null
  status: string | null
  statusSource: string | null
  date: string | null
  route: string | null
  amount?: number
  currencyCode?: string
}

export interface ListData {
  items: ListItem[]
}

export type DashboardWidgetData =
  | KpiData
  | AlertData
  | ChartData
  | GaugeData
  | ListData
  | null

/**
 * Un widget servi.
 *
 * `labelKey` plutôt qu'un libellé : le serveur ne parle pas la langue de qui
 * regarde, et un titre français calculé côté API aurait figé le tableau de bord
 * dans une seule langue.
 */
export interface DashboardWidget {
  key: string
  type: DashboardWidgetType
  labelKey: string
  size: DashboardWidgetSize
  position: number
  route: string | null
  data: DashboardWidgetData
}

export interface DashboardResponse {
  organization: { id: string; name: string } | null
  widgets: DashboardWidget[]
}

/**
 * Une ligne de l'écran de réglage d'un rôle.
 *
 * `availableForRole` est ce qui distingue cet écran du tableau de bord : il
 * montre **tout** le catalogue, y compris ce que le rôle n'a pas le droit de
 * voir. L'interrupteur est alors désactivé et la permission manquante
 * affichée — jamais accordée.
 */
export interface RoleDashboardWidget {
  key: string
  labelKey: string
  descriptionKey: string
  category: DashboardWidgetCategory
  type: DashboardWidgetType
  size: DashboardWidgetSize
  requiredPermission: string
  defaultPosition: number
  position: number
  isEnabled: boolean
  availableForRole: boolean
}

/** Ce qu'on envoie pour enregistrer : une clé, un rang. Rien d'autre. */
export interface RoleDashboardWidgetSelection {
  key: string
  position: number
}
