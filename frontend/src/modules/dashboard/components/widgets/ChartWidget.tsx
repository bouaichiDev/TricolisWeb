import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { AmountsList } from '../charts/AmountsList'
import { ChartLegend } from '../charts/ChartLegend'
import { CompositionBar } from '../charts/CompositionBar'
import {
  OTHER_KEY,
  orderByLifecycle,
  toSlices,
  totalOf,
  type ChartSlice,
} from '../charts/chartPalette'
import { WidgetCard } from '../WidgetCard'
import type { ChartData, ChartSeries, DashboardWidget } from '../../types/dashboard'
import { useStatusOptions } from '@/modules/statuses/hooks/useStatuses'

// Constante partagée plutôt qu'un `[]` littéral : un tableau neuf à chaque
// rendu ferait recalculer les mémoïsations en continu, pour un résultat
// identique.
const NO_SERIES: ChartSeries[] = []

/**
 * Une répartition : une barre de composition, puis sa légende chiffrée.
 *
 * **Sans bibliothèque de graphes**, et c'est un choix, pas un manque. Le projet
 * n'en embarque aucune — seul Leaflet est installé, pour les cartes — et en
 * ajouter une pour une barre segmentée aurait pesé quelques centaines de
 * kilo-octets là où trois `div` suffisent. Un histogramme temporel reposerait la
 * question ; ce serait alors une décision de dépendance, prise comme telle.
 *
 * Deux modes, parce que deux natures de données :
 *
 * - `share` — des parts d'un même tout, qui s'additionnent : la barre les montre
 *   s'additionner ;
 * - `amounts` — des montants dans des devises différentes : **pas de barre**,
 *   une longueur affirmerait une comparaison qui n'existe pas.
 *
 * Les libellés viennent du référentiel des statuts, jamais d'une liste recopiée
 * ici : un statut ajouté par un administrateur s'affiche avec le nom qu'il lui a
 * donné.
 */
export function ChartWidget({ widget }: { widget: DashboardWidget }) {
  const { t, i18n } = useTranslation()
  const [hovered, setHovered] = useState<string | null>(null)

  const data = widget.data as ChartData | null
  const series = data?.series ?? NO_SERIES

  const { statuses } = useStatusOptions(data?.source ?? '')

  // Le référentiel donne l'ordre du cycle de vie ; le serveur, lui, trie par
  // code. `draft` avant `completed` n'a rien d'esthétique : c'est la lecture
  // d'un pipeline, et l'ordre alphabétique la prenait à l'envers.
  const ordered = useMemo(
    () => orderByLifecycle(series, statuses.map((status) => status.code)),
    [series, statuses],
  )
  const slices = useMemo(() => toSlices(ordered), [ordered])

  const shareFormatter = useMemo(
    () => new Intl.NumberFormat(i18n.language, { style: 'percent', maximumFractionDigits: 0 }),
    [i18n.language],
  )

  const labelOf = (slice: ChartSlice) =>
    slice.code === OTHER_KEY
      ? t('dashboard.otherSeries')
      : (statuses.find((status) => status.code === slice.code)?.label ?? slice.code)

  if (series.length === 0) {
    return (
      <WidgetCard title={t(widget.labelKey)} to={widget.route}>
        <p className="text-sm text-muted-foreground">{t('dashboard.widgetEmpty')}</p>
      </WidgetCard>
    )
  }

  if (data?.mode === 'amounts') {
    return (
      <WidgetCard title={t(widget.labelKey)} to={widget.route}>
        <AmountsList series={series} />
      </WidgetCard>
    )
  }

  return (
    <WidgetCard title={t(widget.labelKey)} to={widget.route}>
      {/* Le total en tête : la barre dit comment il se répartit, elle ne dit
          pas de combien il s'agit. Sans lui, deux tableaux de bord aux
          proportions identiques mais aux volumes opposés se ressembleraient. */}
      <span className="text-3xl font-semibold">{totalOf(series)}</span>

      <CompositionBar
        slices={slices}
        hovered={hovered}
        onHover={setHovered}
        labelOf={labelOf}
      />

      <ChartLegend
        slices={slices}
        hovered={hovered}
        onHover={setHovered}
        labelOf={labelOf}
        shareFormatter={shareFormatter}
      />
    </WidgetCard>
  )
}
