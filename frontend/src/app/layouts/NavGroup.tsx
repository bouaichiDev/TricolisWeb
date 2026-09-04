import { ChevronDown } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { NavLink } from 'react-router-dom'

import { menuIcon } from '@/modules/menu/components/menuIcons'
import { menuLabel, type MenuTree } from '@/modules/menu/types/menu'
import { cn } from '@/shared/utils/cn'

/** Style commun aux entrées de premier niveau. */
export function linkClass(isActive: boolean): string {
  return cn(
    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
    isActive
      ? 'bg-sidebar-primary font-medium text-sidebar-primary-foreground'
      : 'text-sidebar-foreground/75 hover:bg-sidebar-accent hover:text-sidebar-foreground',
  )
}

/**
 * Groupe repliable, ouvert d'office quand la route courante lui appartient.
 *
 * Sans cette ouverture, l'utilisateur qui arrive par un lien direct verrait son
 * emplacement replié et se croirait ailleurs.
 */
export function NavGroup({
  node,
  pathname,
  onNavigate,
}: {
  node: MenuTree
  pathname: string
  onNavigate?: () => void
}) {
  const { t } = useTranslation()
  const containsCurrent = node.children.some(
    (child) => child.route !== null && pathname.startsWith(child.route),
  )
  const [open, setOpen] = useState(containsCurrent)
  const Icon = menuIcon(node.item.icon)

  return (
    <div>
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        className={cn(
          'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
          containsCurrent
            ? 'text-sidebar-foreground'
            : 'text-sidebar-foreground/75 hover:text-sidebar-foreground',
        )}
      >
        <Icon className="size-[18px] shrink-0" aria-hidden />
        <span className="min-w-0 flex-1 truncate text-left">{menuLabel(node.item, t)}</span>
        <ChevronDown
          className={cn('size-4 shrink-0 transition-transform', open && 'rotate-180')}
          aria-hidden
        />
      </button>

      {open ? (
        <div className="mt-1 space-y-1 pl-4">
          {node.children.map((child) => (
            <NavLink
              key={child.code}
              to={child.route ?? '/'}
              // `NavLink` marque une entrée active sur ses descendants : c'est
              // ce qu'on veut pour « Clients », actif sur la fiche d'un client.
              // Mais quand une entrée voisine prolonge sa route — « Vue stock »
              // sur `/stock`, « Articles » sur `/stock/items` — les deux
              // s'allument ensemble. La correspondance devient alors exacte,
              // pour celle-là seulement.
              end={node.children.some(
                (sibling) =>
                  sibling.code !== child.code &&
                  child.route !== null &&
                  sibling.route !== null &&
                  sibling.route.startsWith(`${child.route}/`),
              )}
              onClick={onNavigate}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                  isActive
                    ? 'bg-sidebar-primary font-medium text-sidebar-primary-foreground'
                    : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground',
                )
              }
            >
              <span className="size-1.5 shrink-0 rounded-full bg-current opacity-60" aria-hidden />
              <span className="truncate">{menuLabel(child, t)}</span>
            </NavLink>
          ))}
        </div>
      ) : null}
    </div>
  )
}
