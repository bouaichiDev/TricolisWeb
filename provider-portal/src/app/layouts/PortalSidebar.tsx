import { Truck } from 'lucide-react'
import { Link, NavLink } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import { usePlatformLogo } from '@/shared/hooks/usePlatformLogo'
import { cn } from '@/shared/utils/cn'

import { useNavigation, type NavItem } from './navigation'

/** Mêmes classes que les entrées du menu du back-office (`NavGroup.linkClass`). */
function linkClass(isActive: boolean): string {
  return cn(
    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
    isActive
      ? 'bg-sidebar-primary font-medium text-sidebar-primary-foreground'
      : 'text-sidebar-foreground/75 hover:bg-sidebar-accent hover:text-sidebar-foreground',
  )
}

function Entry({ item, onNavigate }: { item: NavItem; onNavigate?: () => void }) {
  const Icon = item.icon

  return (
    <NavLink to={item.path} onClick={onNavigate} className={({ isActive }) => linkClass(isActive)}>
      <Icon className="size-[18px] shrink-0" aria-hidden />
      <span className="truncate">{item.label}</span>
    </NavLink>
  )
}

/**
 * Barre latérale du portail, à la facture du back-office : bleu nuit, marque
 * en tête, entrée active pleine. Les entrées sont celles que le serveur a
 * ouvertes à ce compte (`/navigation`).
 */
export function PortalSidebar({ onNavigate }: { onNavigate?: () => void }) {
  const navigation = useNavigation()
  // Même marque que la barre latérale du back-office : le logo de
  // l'installation sur fond blanc, l'icône livrée à défaut.
  const logo = usePlatformLogo()

  return (
    <div className="bg-sidebar text-sidebar-foreground flex h-full flex-col">
      {logo !== null ? (
        <Link to={paths.dashboard} onClick={onNavigate} className="flex h-16 shrink-0 items-center px-4">
          <span className="flex h-12 w-full items-center justify-center overflow-hidden rounded-lg bg-white px-3 py-2">
            <img src={logo} alt="Tricolis" className="max-h-full max-w-full object-contain" />
          </span>
        </Link>
      ) : (
        <Link to={paths.dashboard} onClick={onNavigate} className="flex h-16 shrink-0 items-center gap-2.5 px-5">
          <Truck className="text-sidebar-primary size-6 shrink-0" aria-hidden />
          <span className="truncate text-lg font-semibold tracking-tight">
            Tricolis <span className="text-sidebar-primary">Fournisseur</span>
          </span>
        </Link>
      )}
      <nav className="flex-1 space-y-1 overflow-y-auto px-3 pb-4" aria-label="Navigation principale">
        {navigation.map((item) => (
          <Entry key={item.key} item={item} onNavigate={onNavigate} />
        ))}
      </nav>
    </div>
  )
}
