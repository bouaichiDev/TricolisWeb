import { useTranslation } from 'react-i18next'

import { ChartFrame } from '../charts/ChartFrame'
import { ColumnStack } from '../charts/ColumnStack'
import { TimeseriesLegend } from '../charts/TimeseriesLegend'
import { useTimeseries } from '../charts/useTimeseries'
import { WidgetCard } from '../WidgetCard'
import type { DashboardWidget, TimeseriesData } from '../../types/dashboard'

/**
 * Un volume quotidien, et sa composition.
 *
 * C'est la seule forme du catalogue qui montre **le temps et la composition à la
 * fois** : la hauteur totale dit combien, l'empilement dit de quoi. Une courbe
 * dirait la tendance mais pas la composition ; un camembert la composition mais
 * pas le jour.
 *
 * Les colonnes occupent le **milieu de leur intervalle** — d'où la convention
 * `(index + 0.5)` passée au cadre. Un point de courbe, lui, se pose sur le
 * bord : les deux ne peuvent pas partager la même règle sans qu'une des deux
 * visées tombe à côté de sa donnée.
 */
export function ColumnsWidget({ widget }: { widget: DashboardWidget }) {
  const { t } = useTranslation()
  const chart = useTimeseries(widget.data as TimeseriesData | null, true)

  if (chart.isEmpty) {
    return (
      <WidgetCard title={t(widget.labelKey)} to={widget.route}>
        <p className="text-sm text-muted-foreground">{t('dashboard.widgetEmpty')}</p>
      </WidgetCard>
    )
  }

  const bandWidth = 100 / chart.data.buckets.length

  return (
    <WidgetCard title={t(widget.labelKey)} to={widget.route}>
      {/* Le jour survolé est écrit ici, au-dessus du graphe : posé en infobulle
          flottante, il aurait recouvert la colonne qu'on vient de viser. */}
      <span className="h-4 text-sm font-medium">{chart.hoveredDay ?? ''}</span>

      <ChartFrame
        buckets={chart.data.buckets}
        ceiling={chart.ceiling}
        ticks={chart.ticks}
        language={chart.language}
        hovered={chart.hovered}
        onHover={chart.setHovered}
        xOf={(index) => (index + 0.5) * bandWidth}
      >
        <ColumnStack
          slices={chart.slices}
          // Transposé : le tracé raisonne par jour, la donnée arrive par série.
          values={chart.data.buckets.map((_, day) =>
            chart.values.map((serie) => serie[day] ?? 0),
          )}
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
