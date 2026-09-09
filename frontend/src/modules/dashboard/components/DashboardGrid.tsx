import { DashboardWidgetRenderer } from './DashboardWidgetRenderer'
import type { DashboardPeriod, DashboardWidget, DashboardWidgetSize } from '../types/dashboard'

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
 *
 * `items-start` n'est pas un détail : sans lui, la grille étire chaque carte sur
 * la hauteur de sa rangée, et un compteur de deux lignes posé à côté d'une
 * jauge devient un rectangle presque vide de trois cents pixels. Chaque carte
 * prend la hauteur de son contenu.
 */
const SPANS: Record<DashboardWidgetSize, string> = {
  small: 'md:col-span-3 lg:col-span-3',
  medium: 'md:col-span-6 lg:col-span-6',
  large: 'md:col-span-6 lg:col-span-8',
  full: 'md:col-span-6 lg:col-span-12',
}

interface DashboardGridProps {
  widgets: DashboardWidget[]
  /**
   * Période réglée en tête d'écran, transmise aux cartes qui forent.
   *
   * Elle ne sert pas à l'affichage — le serveur a déjà calculé ce qu'il fallait
   * — mais au **lien** : une part cliquée doit ouvrir la liste sur la même
   * fenêtre que celle qu'on regarde, sans quoi la liste montrerait plus que la
   * part qu'on vient de viser.
   */
  period?: DashboardPeriod | null
}

export function DashboardGrid({ widgets, period = null }: DashboardGridProps) {
  return (
    <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-6 lg:grid-cols-12">
      {widgets.map((widget) => (
        <div key={widget.key} className={SPANS[widget.size] ?? SPANS.small}>
          <DashboardWidgetRenderer widget={widget} period={period} />
        </div>
      ))}
    </div>
  )
}
