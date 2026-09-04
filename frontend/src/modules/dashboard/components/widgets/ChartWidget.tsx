import { useTranslation } from 'react-i18next'

import { WidgetCard } from '../WidgetCard'
import type { ChartData, DashboardWidget } from '../../types/dashboard'
import { useStatusOptions } from '@/modules/statuses/hooks/useStatuses'

/**
 * Une répartition, en barres.
 *
 * **Sans bibliothèque de graphes**, et c'est un choix, pas un manque. Le projet
 * n'en embarque aucune — seul Leaflet est installé, pour les cartes — et en
 * ajouter une pour tracer des barres proportionnelles aurait pesé quelques
 * centaines de kilo-octets pour ce que trois `div` et une largeur en
 * pourcentage font déjà. Le jour où un histogramme temporel ou un nuage de
 * points sera demandé, la question se reposera pour de bon.
 *
 * Les libellés viennent du **référentiel des statuts**, jamais d'une liste
 * recopiée ici : un statut ajouté par un administrateur s'affiche alors avec le
 * nom qu'il lui a donné. `source` absent — les devises, par exemple — laisse le
 * code parler pour lui-même.
 */
export function ChartWidget({ widget }: { widget: DashboardWidget }) {
  const { t } = useTranslation()
  const data = widget.data as ChartData | null
  const series = data?.series ?? []

  const { statuses } = useStatusOptions(data?.source ?? '')
  const labelOf = (code: string) => statuses.find((status) => status.code === code)?.label ?? code

  // La barre la plus longue occupe toute la largeur : c'est une comparaison
  // entre les séries, pas une mesure absolue. Un maximum nul éviterait une
  // division par zéro, et toutes les barres seraient vides — ce qui est exact.
  const largest = series.reduce((max, item) => Math.max(max, item.value), 0)

  return (
    <WidgetCard title={t(widget.labelKey)} to={widget.route}>
      {series.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('dashboard.widgetEmpty')}</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {series.map((item) => (
            <li key={item.code} className="flex flex-col gap-1">
              <div className="flex items-baseline justify-between gap-2 text-sm">
                <span className="truncate">{labelOf(item.code)}</span>
                <span className="font-medium tabular-nums">{item.value}</span>
              </div>
              <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                <div
                  className="h-full rounded-full bg-primary"
                  style={{ width: largest === 0 ? '0%' : `${(item.value / largest) * 100}%` }}
                />
              </div>
            </li>
          ))}
        </ul>
      )}
    </WidgetCard>
  )
}
