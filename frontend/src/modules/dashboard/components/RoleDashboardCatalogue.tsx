import { useTranslation } from 'react-i18next'

import { RoleDashboardRow } from './RoleDashboardRow'
import type { DashboardWidgetCategory, RoleDashboardWidget } from '../types/dashboard'

interface RoleDashboardCatalogueProps {
  widgets: RoleDashboardWidget[]
  isEnabled: (key: string) => boolean
  disabled: boolean
  onToggle: (widget: RoleDashboardWidget) => void
}

/**
 * L'ordre des catégories, du plus courant au plus rare.
 *
 * Écrit ici plutôt que déduit du serveur : c'est une décision de présentation,
 * et la faire voyager dans chaque réponse aurait ajouté un champ à cinquante
 * lignes pour neuf valeurs qui ne changent pas. Une catégorie absente de cette
 * liste vient en fin — elle s'affiche donc, même livrée avant qu'on y pense.
 */
const ORDER: DashboardWidgetCategory[] = [
  'operations',
  'planning',
  'claims',
  'billing',
  'stock',
  'communications',
  'integrations',
  'administration',
  'quick_actions',
]

/**
 * Tout le catalogue, groupé par métier.
 *
 * **Y compris les widgets décochés**, et y compris ceux que le rôle n'a pas le
 * droit de voir. Les retirer aurait rendu le geste irréversible pour les
 * premiers — plus d'endroit où les remontrer — et muet pour les seconds : on
 * chercherait « Factures brouillon » sans le trouver, sans savoir qu'il ne
 * manque qu'une permission.
 */
export function RoleDashboardCatalogue({
  widgets,
  isEnabled,
  disabled,
  onToggle,
}: RoleDashboardCatalogueProps) {
  const { t } = useTranslation()

  const categories = ORDER.filter((category) => widgets.some((widget) => widget.category === category))

  return (
    <div className="flex flex-col gap-5">
      {categories.map((category) => (
        <section key={category} className="flex flex-col gap-2">
          <h4 className="text-sm font-semibold">{t(`dashboardCategories.${category}`)}</h4>

          <ul className="flex flex-col divide-y rounded-lg border">
            {widgets
              .filter((widget) => widget.category === category)
              .map((widget) => (
                <RoleDashboardRow
                  key={widget.key}
                  widget={widget}
                  isEnabled={isEnabled(widget.key)}
                  disabled={disabled}
                  onToggle={() => onToggle(widget)}
                />
              ))}
          </ul>
        </section>
      ))}
    </div>
  )
}
