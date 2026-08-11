import { useTranslation } from 'react-i18next'
import { NavLink } from 'react-router-dom'

import { navigation } from '@/app/router/navigation'
import { usePermissions } from '@/shared/hooks/usePermission'
import { cn } from '@/shared/utils/cn'

interface AppSidebarProps {
  /** Appelé après un clic : ferme le tiroir sur mobile, sans effet ailleurs. */
  onNavigate?: () => void
}

/**
 * Navigation principale, filtrée par les permissions.
 *
 * Un groupe dont aucune entrée n'est autorisée n'affiche pas son titre : un
 * intitulé « Administration » sans rien dessous laisserait croire à un défaut
 * d'affichage.
 */
export function AppSidebar({ onNavigate }: AppSidebarProps) {
  const { t } = useTranslation()
  const { has } = usePermissions()

  const groups = navigation
    .map((group) => ({ ...group, items: group.items.filter((item) => has(item.permission)) }))
    .filter((group) => group.items.length > 0)

  return (
    <nav className="flex h-full flex-col gap-6 overflow-y-auto p-4" aria-label={t('nav.dashboard')}>
      {groups.map((group) => (
        <div key={group.labelKey} className="flex flex-col gap-1">
          {group.items.length > 1 || group.labelKey !== group.items[0].labelKey ? (
            <p className="px-3 pb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {t(group.labelKey)}
            </p>
          ) : null}

          {group.items.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              onClick={onNavigate}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                  'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                  isActive
                    ? 'bg-sidebar-primary text-sidebar-primary-foreground font-medium'
                    : 'text-sidebar-foreground',
                )
              }
            >
              <item.icon className="size-4 shrink-0" aria-hidden />
              <span className="truncate">{t(item.labelKey)}</span>
            </NavLink>
          ))}
        </div>
      ))}
    </nav>
  )
}
