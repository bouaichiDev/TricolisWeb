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
 * Actions d'une ligne de tableau, en boutons nommés.
 *
 * Chaque action est visible et porte son libellé. Des icônes muettes obligeaient
 * à survoler chacune pour savoir laquelle modifie et laquelle change le statut ;
 * un menu déroulant, lui, cachait l'existence même des actions derrière trois
 * points.
 *
 * La colonne s'élargit d'autant, et c'est assumé : le tableau défile
 * horizontalement, l'utilisateur non.
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
          size="sm"
          className={`whitespace-nowrap ${action.destructive ? 'text-destructive hover:text-destructive' : ''}`}
          onClick={action.onSelect}
        >
          <action.icon className="size-4" aria-hidden />
          {action.label}
        </Button>
      ))}
    </span>
  )
}
