import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { CHART_SLOTS, OTHER_KEY, type ChartSlice } from './chartPalette'
import { formatDay, highestOf, niceScale } from './timeScale'
import { useSeriesLabel } from './useSeriesLabel'
import type { TimeseriesData } from '../../types/dashboard'

const NO_DATA: TimeseriesData = { buckets: [], series: [], source: null, labels: null }

/**
 * Tout ce que les deux graphes temporels partagent.
 *
 * Colonnes et courbes ne diffèrent que par leur tracé — l'échelle, les couleurs,
 * le survol, les libellés et le repli de la queue sont les mêmes. Les écrire
 * deux fois aurait suffi à ce que les deux cartes ne s'arrêtent pas au même
 * plafond, ou que la neuvième série reçoive une teinte d'un côté et pas de
 * l'autre.
 */
export function useTimeseries(raw: TimeseriesData | null, stacked: boolean) {
  const { t, i18n } = useTranslation()
  const [hovered, setHovered] = useState<number | null>(null)

  const data = raw ?? NO_DATA
  const { labelOfCode } = useSeriesLabel({
    mode: 'share',
    source: data.source,
    labels: data.labels,
    series: [],
  })

  /**
   * Au-delà de huit séries, la queue fusionne — comme partout ailleurs. Ici
   * elle s'additionne jour par jour : une neuvième teinte serait indistinguable
   * d'une autre sous vision altérée, et huit courbes sont déjà deux fois trop.
   */
  const { slices, values } = useMemo(() => {
    const kept = data.series.slice(0, CHART_SLOTS)
    const tail = data.series.slice(CHART_SLOTS)

    const slices: ChartSlice[] = kept.map((serie, index) => ({
      code: serie.code,
      value: 0,
      share: 0,
      color: `var(--chart-${index + 1})`,
      isOther: false,
    }))

    const values = kept.map((serie) => serie.values)

    if (tail.length > 0) {
      slices.push({
        code: OTHER_KEY,
        value: 0,
        share: 0,
        color: 'var(--chart-other)',
        isOther: true,
      })

      values.push(
        data.buckets.map((_, day) =>
          tail.reduce((sum, serie) => sum + (serie.values[day] ?? 0), 0),
        ),
      )
    }

    return { slices, values }
  }, [data])

  // L'échelle est calculée sur les séries **après repli** de la queue : ce sont
  // elles qui seront tracées, et le maximum d'une série repliée dans « Autres »
  // n'a plus de hauteur propre.
  const scale = useMemo(
    () =>
      niceScale(
        highestOf(
          { ...data, series: values.map((serie, index) => ({ code: String(index), values: serie })) },
          stacked,
        ),
      ),
    [data, values, stacked],
  )

  return {
    data,
    slices,
    values,
    ceiling: scale.ceiling,
    ticks: scale.ticks,
    hovered,
    setHovered,
    language: i18n.language,
    isEmpty: data.buckets.length === 0 || data.series.length === 0,
    hoveredDay: hovered === null ? null : formatDay(data.buckets[hovered], i18n.language),
    hoveredValues: hovered === null ? [] : values.map((serie) => serie[hovered] ?? 0),
    labelOf: (slice: ChartSlice) =>
      slice.code === OTHER_KEY ? t('dashboard.otherSeries') : labelOfCode(slice.code),
  }
}
