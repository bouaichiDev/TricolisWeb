import type { DashboardPeriod, DashboardWidget, DashboardWidgetSize } from '../types/dashboard'
import { DonutWidget } from './widgets/DonutWidget'
import { KpiWidget, ListWidget, QuickActionWidget } from './widgets/SimpleWidgets'

/** Même grille que le back-office : douze colonnes, la taille vient du catalogue. */
const SPANS: Record<DashboardWidgetSize, string> = {
  small: 'md:col-span-3 lg:col-span-3',
  medium: 'md:col-span-6 lg:col-span-6',
  large: 'md:col-span-6 lg:col-span-8',
  full: 'md:col-span-6 lg:col-span-12',
}

function Renderer({ widget, period }: { widget: DashboardWidget; period: DashboardPeriod | null }) {
  switch (widget.type) {
    case 'kpi':
      return <KpiWidget widget={widget} period={period} />
    case 'donut':
    case 'chart':
      return <DonutWidget widget={widget} period={period} />
    case 'list':
      return <ListWidget widget={widget} period={period} />
    case 'quick_action':
      return <QuickActionWidget widget={widget} period={period} />
    default:
      return null
  }
}

/** L'ordre est celui du serveur : plus petit rang configuré par le transporteur. */
export function DashboardGrid({ widgets, period }: { widgets: DashboardWidget[]; period: DashboardPeriod | null }) {
  return (
    <section aria-label="Indicateurs" className="grid grid-cols-1 items-start gap-4 md:grid-cols-6 lg:grid-cols-12">
      {widgets.map((widget) => (
        <div key={widget.key} className={SPANS[widget.size] ?? SPANS.small}>
          <Renderer widget={widget} period={period} />
        </div>
      ))}
    </section>
  )
}
