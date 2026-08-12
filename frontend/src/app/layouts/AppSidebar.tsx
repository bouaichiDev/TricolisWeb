import { ChevronDown, PanelLeftClose, Truck } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, NavLink, useLocation } from 'react-router-dom'

import {
  homeRoute,
  organizationNavigation,
  platformNavigation,
  type NavEntry,
} from '@/app/router/navigation'
import { usePermissions } from '@/shared/hooks/usePermission'
import { cn } from '@/shared/utils/cn'

interface AppSidebarProps {
  /** Appelé après un clic : ferme le tiroir sur mobile, sans effet ailleurs. */
  onNavigate?: () => void
  onCollapse?: () => void
}

/**
 * Navigation principale — panneau sombre des maquettes.
 *
 * Deux formes d'entrées : simples, et groupes repliables dont les enfants sont
 * indentés. Un groupe s'ouvre automatiquement quand la route courante lui
 * appartient — sinon l'utilisateur qui arrive par un lien direct verrait son
 * emplacement replié et se croirait ailleurs.
 */
export function AppSidebar({ onNavigate, onCollapse }: AppSidebarProps) {
  const { t } = useTranslation()
  const { has, isPlatformAdmin } = usePermissions()
  const location = useLocation()

  /**
   * Le menu dépend d'abord de la portée du compte, ensuite des permissions.
   *
   * Un compte plateforme reçoit le menu plateforme, pas le menu d'organisme
   * expurgé : il administre les organisations inscrites, et les clients, les
   * agences ou les dépôts appartiennent aux organismes. Le lui montrer, même
   * partiellement, lui promettait un périmètre qui n'est pas le sien.
   */
  const tree = isPlatformAdmin ? platformNavigation : organizationNavigation

  const entries = tree
    .map((entry) => ({
      ...entry,
      children: entry.children?.filter((child) => has(child.permission)),
    }))
    .filter((entry) =>
      entry.children ? entry.children.length > 0 : has(entry.permission ?? ''),
    )

  return (
    <div className="flex h-full flex-col bg-sidebar text-sidebar-foreground">
      <Link
        to={homeRoute(isPlatformAdmin)}
        onClick={onNavigate}
        className="flex h-16 shrink-0 items-center gap-2.5 px-5"
      >
        <Truck className="size-6 text-sidebar-primary" aria-hidden />
        <span className="text-lg font-semibold tracking-tight">
          {t('app.name')} <span className="text-sidebar-primary">V2</span>
        </span>
      </Link>

      <nav className="flex-1 space-y-1 overflow-y-auto px-3 pb-4" aria-label={t('nav.main')}>
        {entries.map((entry) =>
          entry.children ? (
            <NavGroup
              key={entry.labelKey}
              entry={entry as NavEntry & { children: NonNullable<NavEntry['children']> }}
              pathname={location.pathname}
              onNavigate={onNavigate}
            />
          ) : (
            <NavLink
              key={entry.to}
              to={entry.to ?? '/'}
              onClick={onNavigate}
              className={({ isActive }) => linkClass(isActive)}
            >
              <entry.icon className="size-[18px] shrink-0" aria-hidden />
              <span className="truncate">{t(entry.labelKey)}</span>
            </NavLink>
          ),
        )}
      </nav>

      {onCollapse ? (
        <button
          type="button"
          onClick={onCollapse}
          className="flex shrink-0 items-center gap-2.5 border-t border-sidebar-border px-5 py-4 text-sm text-sidebar-foreground/70 transition-colors hover:text-sidebar-foreground"
        >
          <PanelLeftClose className="size-4" aria-hidden />
          {t('nav.collapse')}
        </button>
      ) : null}
    </div>
  )
}

function linkClass(isActive: boolean): string {
  return cn(
    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
    isActive
      ? 'bg-sidebar-primary font-medium text-sidebar-primary-foreground'
      : 'text-sidebar-foreground/75 hover:bg-sidebar-accent hover:text-sidebar-foreground',
  )
}

function NavGroup({
  entry,
  pathname,
  onNavigate,
}: {
  entry: NavEntry & { children: NonNullable<NavEntry['children']> }
  pathname: string
  onNavigate?: () => void
}) {
  const { t } = useTranslation()
  const containsCurrent = entry.children.some((child) => pathname.startsWith(child.to))
  const [open, setOpen] = useState(containsCurrent)

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
        <entry.icon className="size-[18px] shrink-0" aria-hidden />
        <span className="min-w-0 flex-1 truncate text-left">{t(entry.labelKey)}</span>
        <ChevronDown
          className={cn('size-4 shrink-0 transition-transform', open && 'rotate-180')}
          aria-hidden
        />
      </button>

      {open ? (
        <div className="mt-1 space-y-1 pl-4">
          {entry.children.map((child) => (
            <NavLink
              key={child.to}
              to={child.to}
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
              <span className="truncate">{t(child.labelKey)}</span>
            </NavLink>
          ))}
        </div>
      ) : null}
    </div>
  )
}
