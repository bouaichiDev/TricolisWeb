import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import type { ChartData, ChartSeries, DashboardWidgetProps } from '../../types/dashboard'
import { drilldownTo } from '../../utils/drilldown'
import { ChartLegend } from '../charts/ChartLegend'
import { DonutChart } from '../charts/DonutChart'
import { OTHER_KEY, toSlices, totalOf, type ChartSlice } from '../charts/chartPalette'
import { WidgetCard } from '../WidgetCard'

const NO_SERIES: ChartSeries[] = []
const share = new Intl.NumberFormat('fr-CH', { style: 'percent', maximumFractionDigits: 0 })

/**
 * Une répartition en anneau, reprise du back-office. Chaque part porte son
 * libellé depuis le serveur ; cliquer une part ouvre la liste filtrée.
 */
export function DonutWidget({ widget, period }: DashboardWidgetProps) {
  const navigate = useNavigate()
  const [hovered, setHovered] = useState<string | null>(null)
  const series = (widget.data as ChartData | null)?.series ?? NO_SERIES
  const slices = useMemo(() => toSlices(series), [series])
  const labels = useMemo(() => new Map(series.map((item) => [item.code, item.label ?? item.code])), [series])

  const labelOf = (slice: ChartSlice) => (slice.code === OTHER_KEY ? 'Autres' : (labels.get(slice.code) ?? slice.code))
  const linkOf = (slice: ChartSlice) => drilldownTo(widget, { code: slice.code, period })

  if (series.length === 0) {
    return (
      <WidgetCard title={widget.label} to={widget.route}>
        <p className="text-muted-foreground text-sm">Rien à afficher pour l’instant.</p>
      </WidgetCard>
    )
  }

  return (
    <WidgetCard title={widget.label} to={widget.route} interactive={widget.drilldown !== null}>
      <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-center">
        <DonutChart
          slices={slices}
          total={totalOf(series)}
          hovered={hovered}
          onHover={setHovered}
          labelOf={labelOf}
          onSelect={(slice) => {
            const to = linkOf(slice)
            if (to !== null) void navigate(to)
          }}
        />
        <div className="w-full min-w-0 flex-1">
          <ChartLegend slices={slices} hovered={hovered} onHover={setHovered} labelOf={labelOf} shareFormatter={share} linkOf={linkOf} />
        </div>
      </div>
    </WidgetCard>
  )
}
