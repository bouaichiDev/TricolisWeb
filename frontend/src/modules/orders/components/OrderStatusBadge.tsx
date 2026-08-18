import { useTranslation } from 'react-i18next'

import { Badge } from '@/shared/components/ui/badge'
import { cn } from '@/shared/utils/cn'

/**
 * Teinte d'un statut de commande.
 *
 * Trois familles seulement : ce qui est en cours, ce qui est achevé, ce qui
 * est arrêté. Donner une couleur propre à chacun des dix statuts ferait un
 * arc-en-ciel où plus rien ne ressort.
 */
const TONE: Record<string, string> = {
  draft: 'bg-muted text-muted-foreground',
  cancelled: 'bg-destructive/10 text-destructive',
  completed: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
  invoiced: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
}

interface OrderStatusBadgeProps {
  status: string | null
  /** Libellé calculé par le backend ; la clé i18n prend le relais s'il manque. */
  label?: string | null
}

export function OrderStatusBadge({ status, label }: OrderStatusBadgeProps) {
  const { t } = useTranslation()

  if (status === null) return <span className="text-muted-foreground">—</span>

  return (
    <Badge
      variant="secondary"
      className={cn('font-normal', TONE[status] ?? 'bg-primary/10 text-primary')}
    >
      {label ?? t(`orderStatuses.${status}`, { defaultValue: status })}
    </Badge>
  )
}

/** Origine d'une commande : saisie interne, portail client, import, API… */
export function OrderSourceBadge({ source }: { source: string | null }) {
  const { t } = useTranslation()

  if (source === null) return <span className="text-muted-foreground">—</span>

  return (
    <Badge variant="outline" className="font-normal">
      {t(`orderSources.${source}`, { defaultValue: source })}
    </Badge>
  )
}
