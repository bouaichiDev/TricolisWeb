import {
  Building2,
  ClipboardList,
  LayoutDashboard,
  Network,
  Shield,
  Users,
  Warehouse,
  type LucideIcon,
} from 'lucide-react'

/**
 * Configuration unique du menu.
 *
 * Le §10 l'exige : une seule source, pas de menu recopié dans plusieurs
 * composants. Chaque entrée porte sa permission, et la barre latérale filtre —
 * un groupe dont aucune entrée n'est autorisée disparaît entièrement plutôt que
 * d'afficher un titre vide.
 *
 * Les codes de permission sont ceux du backend, sans exception : `customers.view`
 * existe dans le seeder, `dashboard.view` aussi.
 */
export interface NavItem {
  /** Clé i18n, jamais un libellé en dur. */
  labelKey: string
  to: string
  icon: LucideIcon
  permission: string
}

export interface NavGroup {
  labelKey: string
  items: NavItem[]
}

export const navigation: NavGroup[] = [
  {
    labelKey: 'nav.dashboard',
    items: [
      {
        labelKey: 'nav.dashboard',
        to: '/dashboard',
        icon: LayoutDashboard,
        permission: 'dashboard.view',
      },
    ],
  },
  {
    labelKey: 'nav.administration',
    items: [
      {
        labelKey: 'nav.organizations',
        to: '/organizations',
        icon: Building2,
        permission: 'organizations.view',
      },
      { labelKey: 'nav.users', to: '/users', icon: Users, permission: 'users.view' },
      { labelKey: 'nav.roles', to: '/roles', icon: Shield, permission: 'roles.view' },
      { labelKey: 'nav.agencies', to: '/agencies', icon: Network, permission: 'agencies.view' },
      { labelKey: 'nav.depots', to: '/depots', icon: Warehouse, permission: 'depots.view' },
    ],
  },
  {
    labelKey: 'nav.clients',
    items: [
      {
        labelKey: 'nav.customers',
        to: '/customers',
        icon: Building2,
        permission: 'customers.view',
      },
    ],
  },
  {
    labelKey: 'nav.system',
    items: [
      {
        labelKey: 'nav.audit',
        to: '/audit',
        icon: ClipboardList,
        permission: 'audit_logs.view',
      },
    ],
  },
]
