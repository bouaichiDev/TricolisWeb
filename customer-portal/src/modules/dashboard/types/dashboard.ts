/**
 * Contrat du tableau de bord, tel que `GET /customer/dashboard` le rend.
 *
 * Même forme que celle du back-office, deux différences près : `label` arrive
 * déjà rédigé (le portail n'a pas de fichier de traduction) et chaque part
 * d'une répartition porte le sien.
 */
export type DashboardWidgetType = 'kpi' | 'donut' | 'list' | 'quick_action' | 'chart'

export type DashboardWidgetSize = 'small' | 'medium' | 'large' | 'full'

export interface KpiData {
  value: number | null
  unit: string | null
}

export interface ChartSeries {
  code: string
  value: number
  label?: string | null
}

export interface ChartData {
  mode: 'share'
  source: string | null
  labels: string | null
  series: ChartSeries[]
}

export interface ListItem {
  id: string
  title: string | null
  subtitle: string | null
  status: string | null
  statusLabel?: string | null
  statusSource: string | null
  date: string | null
  route: string | null
}

export interface ListData {
  items: ListItem[]
}

export interface WidgetDrilldown {
  seriesParam: string | null
  fromParam: string | null
  toParam: string | null
}

export interface DashboardWidget {
  key: string
  type: DashboardWidgetType
  label: string
  size: DashboardWidgetSize
  position: number
  route: string | null
  periodAware: boolean
  drilldown: WidgetDrilldown | null
  data: KpiData | ChartData | ListData | null
}

export interface DashboardPeriod {
  from: string
  to: string
}

export interface DashboardWidgetProps {
  widget: DashboardWidget
  period: DashboardPeriod | null
}

export interface DashboardResponse {
  customer: { id: string; name: string }
  period: DashboardPeriod | null
  widgets: DashboardWidget[]
}
