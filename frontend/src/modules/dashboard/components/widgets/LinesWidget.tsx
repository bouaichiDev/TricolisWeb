import { useTranslation } from 'react-i18next'

import { ChartFrame } from '../charts/ChartFrame'
import { LinePlot } from '../charts/LinePlot'
import { TimeseriesLegend } from '../charts/TimeseriesLegend'
import { useTimeseries } from '../charts/useTimeseries'
import { WidgetCard } from '../WidgetCard'
import type { DashboardWidget, TimeseriesData } from '../../types/dashboard'

/**
 * Une tendance, pas un volume.
 *
 * L'œil suit une pente bien mieux qu'il ne compare trente hauteurs de colonnes
 * voisines : sur un mois, la courbe dit « ça monte » d'un regard là où les
 * colonnes demandent de les lire une à une. C'est pourquoi la fenêtre est plus
 * large ici — trente jours contre quatorze — et pourquoi les séries y sont deux
 * ou trois, jamais huit.
 *
 * **Un seul axe, toujours.** Deux échelles verticales sur un même graphe
 * inventent une corrélation que les données ne portent pas : c'est l'alignement
 * arbitraire des deux échelles qui la fabrique. Les séries servies ici sont donc
 * toujours de même nature — deux comptes de commandes, jamais des commandes
 * contre des tournées.
 *
 * Les points se posent **sur le bord** du cadre, le premier à 0 %, le dernier à
 * 100 % : une courbe relie des instants, elle n'occupe pas des intervalles.
 */
export function LinesWidget({ widget }: { widget: DashboardWidget }) {
  const { t } = useTranslation()
  const chart = useTimeseries(widget.data as TimeseriesData | null, false)

  if (chart.isEmpty) {
    return (
      <WidgetCard title={t(widget.labelKey)} to={widget.route}>
        <p className="text-sm text-muted-foreground">{t('dashboard.widgetEmpty')}</p>
      </WidgetCard>
    )
  }

  const count = chart.data.buckets.length

  return (
    <WidgetCard title={t(widget.labelKey)} to={widget.route}>
      <span className="h-4 text-sm font-medium">{chart.hoveredDay ?? ''}</span>

      <ChartFrame
        buckets={chart.data.buckets}
        ceiling={chart.ceiling}
        ticks={chart.ticks}
        language={chart.language}
        hovered={chart.hovered}
        onHover={chart.setHovered}
        xOf={(index) => (count <= 1 ? 50 : (index / (count - 1)) * 100)}
      >
        <LinePlot
          slices={chart.slices}
          values={chart.values}
          ceiling={chart.ceiling}
          hovered={chart.hovered}
        />
      </ChartFrame>

      <TimeseriesLegend
        slices={chart.slices}
        values={chart.hoveredValues}
        labelOf={chart.labelOf}
      />
    </WidgetCard>
  )
}
