import { Badge } from '@/shared/components/ui/badge'
import { cn } from '@/shared/utils/cn'

import { statusAppearance } from './statusTones'

interface StatusBadgeProps {
  code: string | null | undefined
  label?: string | null
  /** Couleur du référentiel (`#RRGGBB`), quand l'administrateur en a réglé une. */
  color?: string | null
}

/**
 * Badge de statut. Le libellé et la couleur viennent du référentiel ; sans
 * couleur réglée, la teinte de repli du back-office.
 */
export function StatusBadge({ code, label, color }: StatusBadgeProps) {
  if (!code) return <span className="text-muted-foreground">—</span>

  const appearance = statusAppearance(code, color)

  return (
    <Badge variant="outline" className={cn('font-medium', appearance.className)} style={appearance.style}>
      {label ?? code}
    </Badge>
  )
}
