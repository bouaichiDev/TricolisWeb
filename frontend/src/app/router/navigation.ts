import {
  Route,
  Boxes,
  ReceiptText,
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
 * **Deux menus, pas un menu filtré.** L'administration de la plateforme et
 * celle d'un organisme ne sont pas deux niveaux de la même chose : un compte
 * plateforme gère les organisations inscrites, et n'a que faire des clients,
 * des agences ou des dépôts — qui appartiennent aux organismes, pas à lui.
 * Filtrer entrée par entrée produisait un menu d'organisme amputé, où le compte
 * plateforme voyait « Clients » et « Mon organisation » sans raison.
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

/**
 * Menu d'un organisme.
 *
 * Son activité : ses clients, ses ressources, ses utilisateurs, ses rôles.
 * « Mon organisation » remplace l'annuaire global — un organisme n'a accès
 * qu'à la sienne.
 */
export const organizationNavigation: NavEntry[] = [
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
      { labelKey: 'nav.providers', to: '/providers', permission: 'providers.view' },
      { labelKey: 'nav.drivers', to: '/drivers', permission: 'drivers.view' },
      { labelKey: 'nav.vehicles', to: '/vehicles', permission: 'vehicles.view' },
      { labelKey: 'nav.types', to: '/types', permission: 'types.view' },
    ],
  },
  {
    labelKey: 'nav.transport',
    icon: Route,
    children: [
      { labelKey: 'nav.planning', to: '/planning', permission: 'tours.view' },
      { labelKey: 'nav.tours', to: '/tours', permission: 'tours.view' },
    ],
  },
  {
    labelKey: 'nav.billing',
    icon: ReceiptText,
    children: [
      { labelKey: 'nav.invoices', to: '/billing/invoices', permission: 'invoices.view' },
      {
        labelKey: 'nav.settlements',
        to: '/billing/settlements',
        permission: 'provider_settlements.view',
      },
      {
        labelKey: 'nav.exportConfigurations',
        to: '/billing/export-configurations',
        permission: 'customer_export_configurations.view',
      },
      { labelKey: 'nav.exports', to: '/billing/exports', permission: 'export_jobs.view' },
    ],
  },
  {
    labelKey: 'nav.administration',
    icon: Settings,
    children: [
      { labelKey: 'nav.myOrganization', to: '/my-organization', permission: 'organizations.view' },
      { labelKey: 'nav.users', to: '/users', permission: 'users.view' },
      { labelKey: 'nav.roles', to: '/roles', permission: 'roles.view' },
      { labelKey: 'nav.audit', to: '/audit', permission: 'audit.view' },
    ],
  },
]

/**
 * Menu de la plateforme.
 *
 * Une seule entrée, et c'est voulu : un compte plateforme administre les
 * organisations inscrites. Les utilisateurs, les rôles et le journal d'audit
 * sont portés par une organisation — les proposer ici obligerait à en désigner
 * une, ce qui n'a pas de sens depuis la plateforme.
 */
export const platformNavigation: NavEntry[] = [
  {
    labelKey: 'nav.organizations',
    icon: Building2,
    to: '/organizations',
    permission: 'organizations.view',
  },
]

/**
 * Page d'accueil selon la portée du compte.
 *
 * Un compte plateforme n'a pas de tableau de bord : celui-ci compte des clients
 * et des agences, qui appartiennent aux organismes. Son point d'entrée est la
 * liste des organisations inscrites.
 */
export function homeRoute(isPlatformAdmin: boolean): string {
  return isPlatformAdmin ? '/organizations' : '/dashboard'
}

/** Icônes des entrées simples, pour le fil d'Ariane et les titres de page. */
export const sectionIcons: Record<string, LucideIcon> = {
  '/dashboard': LayoutDashboard,
  '/customers': Building2,
  '/agencies': Network,
  '/depots': Warehouse,
  '/users': Users,
  '/roles': Shield,
  '/organizations': Building2,
  '/my-organization': Building2,
  '/audit': ClipboardList,
}
