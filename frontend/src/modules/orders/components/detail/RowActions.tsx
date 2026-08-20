import { MoreHorizontal, type LucideIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/shared/components/ui/dropdown-menu'
import { usePermissions } from '@/shared/hooks/usePermission'

export interface RowAction {
  key: string
  label: string
  icon: LucideIcon
  onSelect: () => void
  /** Permission requise ; l'action disparaît sans elle. */
  permission?: string
  /** Colore l'entrée en rouge : suppression. */
  destructive?: boolean
}

/**
 * Actions d'une ligne de tableau, dans un menu nommé.
 *
 * Des icônes muettes alignées en bout de ligne ne se lisent pas : il faut
 * survoler chacune pour savoir laquelle modifie et laquelle change le statut.
 * Le menu les nomme, tient dans une colonne étroite, et accueille les actions
 * suivantes sans élargir la ligne.
 *
 * Une action dont la permission manque n'est pas grisée : elle disparaît. Le
 * menu vide disparaît à son tour, plutôt que de s'ouvrir sur rien.
 */
export function RowActions({ actions }: { actions: RowAction[] }) {
  const { t } = useTranslation()
  const { has } = usePermissions()

  const allowed = actions.filter(
    (action) => action.permission === undefined || has(action.permission),
  )

  if (allowed.length === 0) return null

  return (
    <span className="flex justify-end">
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button type="button" variant="ghost" size="sm" aria-label={t('common.actions')}>
            <MoreHorizontal className="size-4" aria-hidden />
          </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end">
          {allowed.map((action) => (
            <DropdownMenuItem
              key={action.key}
              onSelect={action.onSelect}
              variant={action.destructive ? 'destructive' : 'default'}
            >
              <action.icon className="size-4" aria-hidden />
              {action.label}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </span>
  )
}
