import { PanelLeftClose, Truck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, NavLink, useLocation } from 'react-router-dom'

import { linkClass, NavGroup } from './NavGroup'
import { homeRoute } from '@/app/router/navigation'
import { menuIcon } from '@/modules/menu/components/menuIcons'
import { useMenu } from '@/modules/menu/hooks/useMenu'
import { buildMenuTree } from '@/modules/menu/types/menu'
import { Skeleton } from '@/shared/components/ui/skeleton'
import { usePermissions } from '@/shared/hooks/usePermission'

interface AppSidebarProps {
  /** Appelé après un clic : ferme le tiroir sur mobile, sans effet ailleurs. */
  onNavigate?: () => void
  onCollapse?: () => void
}

/**
 * Navigation principale — panneau sombre des maquettes.
 *
 * Le menu vient de `GET /menu` et non plus d'une liste écrite ici. Le backend
 * y applique trois filtres dans l'ordre : la portée du compte — plateforme ou
 * organisme —, les entrées que l'organisation a choisi de masquer, puis les
 * permissions de l'utilisateur.
 *
 * Ce déplacement a une raison précise : chaque organisme veut son menu. Un
 * transporteur qui n'utilise pas une fonction n'a pas à en voir l'entrée, et ce
 * choix ne peut pas vivre dans un fichier livré à tout le monde.
 */
export function AppSidebar({ onNavigate, onCollapse }: AppSidebarProps) {
  const { t } = useTranslation()
  const { isPlatformAdmin } = usePermissions()
  const location = useLocation()

  const { data, isPending } = useMenu()
  const tree = buildMenuTree(data ?? [])

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
        {isPending
          ? Array.from({ length: 4 }, (_, index) => (
              <Skeleton key={index} className="h-10 w-full bg-sidebar-accent" />
            ))
          : tree.map((node) => {
              if (node.item.route === null) {
                return (
                  <NavGroup
                    key={node.item.code}
                    node={node}
                    pathname={location.pathname}
                    onNavigate={onNavigate}
                  />
                )
              }

              const Icon = menuIcon(node.item.icon)

              return (
                <NavLink
                  key={node.item.code}
                  to={node.item.route}
                  onClick={onNavigate}
                  className={({ isActive }) => linkClass(isActive)}
                >
                  <Icon className="size-[18px] shrink-0" aria-hidden />
                  <span className="truncate">{t(node.item.labelKey)}</span>
                </NavLink>
              )
            })}
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
