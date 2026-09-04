import { DashboardWidgetRenderer } from './DashboardWidgetRenderer'
import type { DashboardWidget, DashboardWidgetSize } from '../types/dashboard'

/**
 * Douze colonnes sur un écran large, six sur une tablette, une sur un
 * téléphone.
 *
 * La taille vient du **catalogue**, pas de la configuration. L'administrateur
 * choisit ce qu'un rôle voit et dans quel ordre ; lui laisser régler la largeur
 * de chaque carte aurait demandé un éditeur de page, pour un besoin qui est de
 * composer une vue métier — et aurait permis de rendre illisible un tableau de
 * bord qu'on ne peut plus corriger qu'en le rouvrant.
 *
 * L'ordre est celui que le serveur a rendu : plus petit rang configuré, la clé
 * départageant les égalités. Retrier ici aurait donné un second ordre, à
 * défendre contre le premier.
 */
const SPANS: Record<DashboardWidgetSize, string> = {
  small: 'md:col-span-3 lg:col-span-3',
  medium: 'md:col-span-6 lg:col-span-6',
  large: 'md:col-span-6 lg:col-span-8',
  full: 'md:col-span-6 lg:col-span-12',
}

export function DashboardGrid({ widgets }: { widgets: DashboardWidget[] }) {
  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-6 lg:grid-cols-12">
      {widgets.map((widget) => (
        <div key={widget.key} className={SPANS[widget.size] ?? SPANS.small}>
          <DashboardWidgetRenderer widget={widget} />
        </div>
      ))}
    </div>
  )
}
