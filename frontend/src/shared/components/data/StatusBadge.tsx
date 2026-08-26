import { useTranslation } from 'react-i18next'

import { useStatusLabel } from '@/modules/statuses/hooks/useStatuses'
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

interface StatusBadgeProps {
  status: string | null | undefined
  /**
   * Entite concernee, quand son statut est decrit au referentiel.
   *
   * Le libelle vient alors de `statuses` plutot que des cles i18n : c'est la
   * seule facon qu'un statut ajoute par un administrateur s'affiche avec son
   * nom. Sans `source`, le comportement d'origine est conserve — plusieurs
   * champs `status` sont encore des chaines libres.
   */
  source?: string
}

export function StatusBadge({ status, source }: StatusBadgeProps) {
  const { t } = useTranslation()
  const referential = useStatusLabel(source ?? '', source === undefined ? null : status)

  if (!status) return <span className="text-muted-foreground">—</span>

  const key = status.toLowerCase()
  const label = referential ?? t(`status.${key}`, { defaultValue: status })

  return (
    <Badge variant="outline" className={cn('font-medium', TONES[key] ?? TONES.inactive)}>
      {label}
    </Badge>
  )
}
