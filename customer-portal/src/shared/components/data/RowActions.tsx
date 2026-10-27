import type { LucideIcon } from 'lucide-react'
import { Link } from 'react-router-dom'

import { Can } from '@/app/guards/Can'
import { Button } from '@/shared/components/ui/button'
import { cn } from '@/shared/utils/cn'

export interface RowAction {
  key: string
  icon: LucideIcon
  label: string
  to?: string
  onClick?: () => void
  /** Droit(s) sans lequel l'action n'est pas proposée. */
  permission?: string | readonly string[]
  tone?: 'default' | 'danger'
  disabled?: boolean
}

/** Boutons-icônes en bout de ligne, comme au back-office. */
export function RowActions({ actions }: { actions: RowAction[] }) {
  return (
    <span className="flex justify-end gap-0.5">
      {actions.map((action) => {
        const button = (
          <Button
            key={action.key}
            asChild={action.to !== undefined && !action.disabled}
            variant="ghost"
            size="icon"
            title={action.label}
            aria-label={action.label}
            disabled={action.disabled}
            className={cn('size-8', action.tone === 'danger' && 'text-destructive hover:text-destructive')}
            onClick={action.onClick}
          >
            {action.to !== undefined && !action.disabled ? (
              <Link to={action.to}>
                <action.icon className="size-4" aria-hidden />
              </Link>
            ) : (
              <action.icon className="size-4" aria-hidden />
            )}
          </Button>
        )

        return action.permission ? (
          <Can key={action.key} permission={action.permission}>
            {button}
          </Can>
        ) : (
          button
        )
      })}
    </span>
  )
}
