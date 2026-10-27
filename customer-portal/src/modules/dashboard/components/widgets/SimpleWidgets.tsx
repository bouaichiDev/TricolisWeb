import { Plus } from 'lucide-react'
import { Link } from 'react-router-dom'

import { Badge } from '@/shared/components/ui/badge'
import { formatDate } from '@/shared/utils/format'

import type { DashboardWidgetProps, KpiData, ListData } from '../../types/dashboard'
import { WidgetCard } from '../WidgetCard'

/**
 * Un chiffre. Une valeur absente affiche un tiret, jamais un zéro : « aucune
 * commande » et « le chiffre n'a pas pu être lu » ne disent pas la même chose.
 */
export function KpiWidget({ widget }: DashboardWidgetProps) {
  const data = widget.data as KpiData | null

  return (
    <WidgetCard title={widget.label} to={widget.route}>
      <span className="text-3xl font-semibold tabular-nums">
        {data?.value ?? '—'}
        {data?.unit ? <span className="text-muted-foreground ml-1 text-base font-normal">{data.unit}</span> : null}
      </span>
    </WidgetCard>
  )
}

/** Un raccourci : aucune donnée, un libellé et une destination. */
export function QuickActionWidget({ widget }: DashboardWidgetProps) {
  return (
    <WidgetCard title="Action rapide" to={widget.route}>
      <span className="flex items-center gap-2 text-base font-medium">
        <Plus className="text-primary size-4 shrink-0" aria-hidden />
        {widget.label}
      </span>
    </WidgetCard>
  )
}

/** Quelques lignes, chacune vers sa fiche, et un lien vers la liste entière. */
export function ListWidget({ widget }: DashboardWidgetProps) {
  const items = (widget.data as ListData | null)?.items ?? []

  return (
    <WidgetCard title={widget.label}>
      {items.length === 0 ? (
        <p className="text-muted-foreground text-sm">Rien à afficher pour l’instant.</p>
      ) : (
        <ul className="flex flex-col divide-y">
          {items.map((item) => (
            <li key={item.id} className="py-2">
              <Link to={item.route ?? '#'} className="flex items-center justify-between gap-3 hover:underline">
                <span className="flex min-w-0 flex-col">
                  <span className="truncate text-sm font-medium">{item.title ?? '—'}</span>
                  <span className="text-muted-foreground truncate text-xs">
                    {[item.subtitle, formatDate(item.date)].filter(Boolean).join(' · ')}
                  </span>
                </span>
                {item.status && <Badge variant="secondary">{item.statusLabel ?? item.status}</Badge>}
              </Link>
            </li>
          ))}
        </ul>
      )}
      {widget.route && (
        <Link to={widget.route} className="text-primary mt-auto pt-1 text-sm font-medium hover:underline">
          Tout voir
        </Link>
      )}
    </WidgetCard>
  )
}
