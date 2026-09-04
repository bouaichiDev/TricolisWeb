import { useTranslation } from 'react-i18next'

import { WidgetCard } from '../WidgetCard'
import type { DashboardWidget, KpiData } from '../../types/dashboard'

/**
 * Un chiffre, et de quoi savoir ce qu'il compte.
 *
 * **Aucune tendance.** Un « +12 % » sous un compteur se lit comme une mesure,
 * et il n'y en a pas : le backend ne compare pas deux périodes, et l'inventer
 * ici aurait produit un chiffre décoratif que quelqu'un finirait par citer en
 * réunion. Le jour où la comparaison existera vraiment, elle viendra du
 * serveur.
 *
 * Une valeur absente affiche un tiret, jamais un zéro : « aucun client » et
 * « le chiffre n'a pas pu être lu » ne sont pas la même information.
 */
export function KpiWidget({ widget }: { widget: DashboardWidget }) {
  const { t } = useTranslation()
  const data = widget.data as KpiData | null

  return (
    <WidgetCard title={t(widget.labelKey)} to={widget.route}>
      <span className="text-3xl font-semibold tabular-nums">
        {data?.value ?? '—'}
        {data?.unit ? <span className="ml-1 text-base font-normal text-muted-foreground">{data.unit}</span> : null}
      </span>
    </WidgetCard>
  )
}
