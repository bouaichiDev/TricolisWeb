import { useTranslation } from 'react-i18next'

import { Badge } from '@/shared/components/ui/badge'
import { cn } from '@/shared/utils/cn'

/**
 * Pastille de statut.
 *
 * Les couleurs traduisent une intention metier, pas une valeur : actif est
 * vert, bloque est rouge, en attente est ambre. Un statut inconnu tombe en
 * gris plutot que d'echouer — le backend laisse plusieurs champs `status` en
 * chaine libre, et inventer une couleur pour chacun serait faux.
 */
const TONES: Record<string, string> = {
  active: 'bg-success/12 text-success border-success/25',
  inactive: 'bg-muted text-muted-foreground border-border',
  pending: 'bg-warning/15 text-warning border-warning/30',
  invited: 'bg-warning/15 text-warning border-warning/30',
  suspended: 'bg-destructive/12 text-destructive border-destructive/25',
  blocked: 'bg-destructive/12 text-destructive border-destructive/25',
  closed: 'bg-muted text-muted-foreground border-border',
  disabled: 'bg-muted text-muted-foreground border-border',
}

export function StatusBadge({ status }: { status: string | null | undefined }) {
  const { t } = useTranslation()

  if (!status) return <span className="text-muted-foreground">—</span>

  const key = status.toLowerCase()
  const label = t(`status.${key}`, { defaultValue: status })

  return (
    <Badge variant="outline" className={cn('font-medium', TONES[key] ?? TONES.inactive)}>
      {label}
    </Badge>
  )
}
