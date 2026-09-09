import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { ChartLegend } from '../charts/ChartLegend'
import { DonutChart } from '../charts/DonutChart'
import {
  OTHER_KEY,
  orderByLifecycle,
  toSlices,
  totalOf,
  type ChartSlice,
} from '../charts/chartPalette'
import { useSeriesLabel } from '../charts/useSeriesLabel'
import { WidgetCard } from '../WidgetCard'
import { drilldownTo } from '../../utils/drilldown'
import { widgetTitle } from '../../utils/widgetTitle'
import type { ChartData, ChartSeries, DashboardWidgetProps } from '../../types/dashboard'

const NO_SERIES: ChartSeries[] = []

/**
 * Peu de parts, lues d'un coup d'œil.
 *
 * La différence avec `ChartWidget` n'est pas cosmétique, et elle est prise **au
 * catalogue**, pas ici : le camembert est réservé aux séries qui ne peuvent pas
 * dépasser six parts — les six statuts d'une tournée, les cinq canaux d'envoi.
 * Au-delà, deux secteurs voisins deviennent indistinguables, et la barre de
 * composition dit mieux la même chose.
 *
 * La légende reste **la même** que celle de la barre, et c'est voulu : c'est
 * elle qui porte les valeurs au chiffre près. Un camembert seul ne permet pas de
 * comparer deux parts proches — c'est son défaut connu, et la liste chiffrée à
 * côté est la réponse, pas un ornement.
 */
export function DonutWidget({ widget, period }: DashboardWidgetProps) {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const [hovered, setHovered] = useState<string | null>(null)

  const data = widget.data as ChartData | null
  const series = data?.series ?? NO_SERIES

  const { labelOfCode, referentialOrder } = useSeriesLabel(data)

  const ordered = useMemo(
    () => orderByLifecycle(series, referentialOrder),
    [series, referentialOrder],
  )
  const slices = useMemo(() => toSlices(ordered), [ordered])

  const shareFormatter = useMemo(
    () => new Intl.NumberFormat(i18n.language, { style: 'percent', maximumFractionDigits: 0 }),
    [i18n.language],
  )

  const labelOf = (slice: ChartSlice) =>
    slice.code === OTHER_KEY ? t('dashboard.otherSeries') : labelOfCode(slice.code)

  const linkOf = (slice: ChartSlice) => drilldownTo(widget, { code: slice.code, period })

  const open = (slice: ChartSlice) => {
    const to = linkOf(slice)

    if (to !== null) void navigate(to)
  }

  if (series.length === 0) {
    return (
      <WidgetCard title={widgetTitle(widget, period, t)} to={widget.route}>
        <p className="text-sm text-muted-foreground">{t('dashboard.widgetEmpty')}</p>
      </WidgetCard>
    )
  }

  return (
    <WidgetCard
      title={widgetTitle(widget, period, t)}
      to={widget.route}
      interactive={widget.drilldown !== null}
    >
      {/* L'anneau et sa légende côte à côte au-delà du téléphone, l'un sous
          l'autre en dessous : une colonne de 300 px ne tient pas les deux sans
          que les libellés se coupent. */}
      <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-center">
        <DonutChart
          slices={slices}
          total={totalOf(series)}
          hovered={hovered}
          onHover={setHovered}
          labelOf={labelOf}
          onSelect={widget.drilldown === null ? undefined : open}
        />

        <div className="w-full min-w-0 flex-1">
          <ChartLegend
            slices={slices}
            hovered={hovered}
            onHover={setHovered}
            labelOf={labelOf}
            shareFormatter={shareFormatter}
            linkOf={linkOf}
          />
        </div>
      </div>
    </WidgetCard>
  )
}
