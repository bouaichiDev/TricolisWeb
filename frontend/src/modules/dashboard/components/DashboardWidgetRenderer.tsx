import { AlertWidget } from './widgets/AlertWidget'
import { ChartWidget } from './widgets/ChartWidget'
import { KpiWidget } from './widgets/KpiWidget'
import { ListWidget } from './widgets/ListWidget'
import { QuickActionWidget } from './widgets/QuickActionWidget'
import type { DashboardWidget } from '../types/dashboard'

/**
 * Du type à son composant, et **rien d'autre**.
 *
 * Cinq valeurs, cinq composants écrits ici. Aucun nom de composant ne voyage
 * depuis la base : c'est la garantie qu'une configuration, même écrite à la
 * main dans la table, ne peut pas faire rendre autre chose que ces cinq-là. Un
 * `components[widget.component]` aurait suffi à ouvrir cette porte.
 *
 * Un type inconnu ne rend **rien**. Le cas ne devrait pas se produire — le
 * serveur ne sert que ce que son énumération contient — mais un déploiement
 * frontend en retard d'une version le verrait, et une carte manquante vaut
 * mieux qu'un écran blanc.
 */
export function DashboardWidgetRenderer({ widget }: { widget: DashboardWidget }) {
  switch (widget.type) {
    case 'kpi':
      return <KpiWidget widget={widget} />
    case 'alert':
      return <AlertWidget widget={widget} />
    case 'chart':
      return <ChartWidget widget={widget} />
    case 'list':
      return <ListWidget widget={widget} />
    case 'quick_action':
      return <QuickActionWidget widget={widget} />
    default:
      return null
  }
}
