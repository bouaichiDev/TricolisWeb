import { AlertWidget } from './widgets/AlertWidget'
import { ChartWidget } from './widgets/ChartWidget'
import { ColumnsWidget } from './widgets/ColumnsWidget'
import { DonutWidget } from './widgets/DonutWidget'
import { GaugeWidget } from './widgets/GaugeWidget'
import { KpiWidget } from './widgets/KpiWidget'
import { LinesWidget } from './widgets/LinesWidget'
import { ListWidget } from './widgets/ListWidget'
import { QuickActionWidget } from './widgets/QuickActionWidget'
import type { DashboardPeriod, DashboardWidget } from '../types/dashboard'

/**
 * Du type à son composant, et **rien d'autre**.
 *
 * Neuf valeurs, neuf composants écrits ici. Aucun nom de composant ne voyage
 * depuis la base : c'est la garantie qu'une configuration, même écrite à la
 * main dans la table, ne peut pas faire rendre autre chose que ces neuf-là. Un
 * `components[widget.component]` aurait suffi à ouvrir cette porte.
 *
 * Un type inconnu ne rend **rien**. Le cas ne devrait pas se produire — le
 * serveur ne sert que ce que son énumération contient — mais un déploiement
 * frontend en retard d'une version le verrait, et une carte manquante vaut
 * mieux qu'un écran blanc.
 */
interface RendererProps {
  widget: DashboardWidget
  /** Période de l'écran, que seules les cartes qui forent utilisent. */
  period: DashboardPeriod | null
}

export function DashboardWidgetRenderer({ widget, period }: RendererProps) {
  switch (widget.type) {
    case 'kpi':
      return <KpiWidget widget={widget} period={period} />
    case 'alert':
      return <AlertWidget widget={widget} period={period} />
    case 'chart':
      return <ChartWidget widget={widget} period={period} />
    case 'donut':
      return <DonutWidget widget={widget} period={period} />
    case 'gauge':
      return <GaugeWidget widget={widget} period={period} />
    case 'columns':
      return <ColumnsWidget widget={widget} period={period} />
    case 'lines':
      return <LinesWidget widget={widget} period={period} />
    case 'list':
      return <ListWidget widget={widget} period={period} />
    case 'quick_action':
      return <QuickActionWidget widget={widget} period={period} />
    default:
      return null
  }
}
