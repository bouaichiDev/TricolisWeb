import {
  Boxes,
  Building2,
  ClipboardList,
  LayoutDashboard,
  Network,
  Settings,
  Shield,
  Users,
  Warehouse,
  type LucideIcon,
} from 'lucide-react'

/**
 * Configuration unique du menu.
 *
 * Le §10 l'exige : une seule source, jamais un menu recopié dans plusieurs
 * composants. Chaque entrée porte sa permission ; la barre latérale filtre, et
 * un groupe dont aucune entrée n'est autorisée disparaît entièrement plutôt que
 * d'afficher un titre vide.
 *
 * Les maquettes distinguent deux formes : des entrées de premier niveau, et un
 * groupe « Administration » repliable dont les enfants sont indentés. La
 * structure ci-dessous porte cette distinction — `children` absent signifie une
 * entrée simple.
 */
export interface NavItem {
  /** Clé i18n, jamais un libellé en dur. */
  labelKey: string
  to: string
  permission: string
}

export interface NavEntry {
  labelKey: string
  icon: LucideIcon
  /** Route pour une entrée simple ; absent pour un groupe repliable. */
  to?: string
  permission?: string
  children?: NavItem[]
}

export const navigation: NavEntry[] = [
  {
    labelKey: 'nav.dashboard',
    icon: LayoutDashboard,
    to: '/dashboard',
    permission: 'dashboard.view',
  },
  {
    labelKey: 'nav.customers',
    icon: Building2,
    to: '/customers',
    permission: 'customers.view',
  },
  {
    labelKey: 'nav.resources',
    icon: Boxes,
    children: [
      { labelKey: 'nav.agencies', to: '/agencies', permission: 'agencies.view' },
      { labelKey: 'nav.depots', to: '/depots', permission: 'depots.view' },
    ],
  },
  {
    labelKey: 'nav.administration',
    icon: Settings,
    children: [
      { labelKey: 'nav.users', to: '/users', permission: 'users.view' },
      { labelKey: 'nav.roles', to: '/roles', permission: 'roles.view' },
      { labelKey: 'nav.organizations', to: '/organizations', permission: 'organizations.view' },
      { labelKey: 'nav.audit', to: '/audit', permission: 'audit_logs.view' },
    ],
  },
]

/** Icônes des entrées simples, pour le fil d'Ariane et les titres de page. */
export const sectionIcons: Record<string, LucideIcon> = {
  '/dashboard': LayoutDashboard,
  '/customers': Building2,
  '/agencies': Network,
  '/depots': Warehouse,
  '/users': Users,
  '/roles': Shield,
  '/organizations': Building2,
  '/audit': ClipboardList,
}
