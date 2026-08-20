import type { LucideIcon } from 'lucide-react'

import { Button } from '@/shared/components/ui/button'
import { usePermissions } from '@/shared/hooks/usePermission'

export interface RowAction {
  key: string
  label: string
  icon: LucideIcon
  onSelect: () => void
  /** Permission requise ; l'action disparaît sans elle. */
  permission?: string
  /** Colore le bouton en rouge : suppression. */
  destructive?: boolean
}

/**
 * Actions d'une ligne de tableau, en icônes.
 *
 * Toutes sont visibles côte à côte : un menu déroulant cacherait leur existence
 * même derrière trois points. Mais cinq libellés par ligne poussaient le reste
 * du tableau hors de l'écran, alors que ce sont les colonnes de données qu'on
 * vient lire.
 *
 * Le libellé n'est pas perdu pour autant : `title` l'affiche au survol,
 * `aria-label` le donne au lecteur d'écran. Une icône seule et muette, elle,
 * obligerait à cliquer pour savoir.
 *
 * Une action dont la permission manque n'est pas grisée : elle disparaît.
 */
export function RowActions({ actions }: { actions: RowAction[] }) {
  const { has } = usePermissions()

  const allowed = actions.filter(
    (action) => action.permission === undefined || has(action.permission),
  )

  if (allowed.length === 0) return null

  return (
    <span className="flex justify-end gap-1">
      {allowed.map((action) => (
        <Button
          key={action.key}
          type="button"
          variant="ghost"
          size="icon"
          className={action.destructive ? 'text-destructive hover:text-destructive' : undefined}
          onClick={action.onSelect}
          title={action.label}
          aria-label={action.label}
        >
          <action.icon className="size-4" aria-hidden />
        </Button>
      ))}
    </span>
  )
}
