/** Permission — champs relevés sur `PermissionResource`. */
export interface Permission {
  id: string
  code: string
  name: string
  module: string
  /**
   * Découpe métier, distincte du module qui est technique.
   *
   * Le référentiel compte 48 modules — `tour_stop_services`,
   * `provider_settlement_lines` — et une dizaine de sections. Grouper le
   * formulaire de rôle sur le module produisait 48 blocs, dans lesquels
   * composer un rôle était impraticable.
   */
  menuSection: string | null
  action: string
}

/**
 * Ordre d'affichage des sections, calqué sur `MenuSection::position()`.
 *
 * L'ordre alphabétique placerait « Administration » en tête, devant
 * « Clients » — ce que personne ne cherche en premier.
 */
export const MENU_SECTION_ORDER = [
  'dashboard',
  'customers',
  'resources',
  'operations',
  'stock',
  'billing',
  'communications',
  'integrations',
  'administration',
  'platform',
] as const

/**
 * Rôle — champs relevés sur `RoleResource`.
 *
 * `organizationId` est nullable : un rôle de portée plateforme n'appartient à
 * aucune organisation. Un administrateur d'organisme n'en voit jamais, la liste
 * étant bornée à son organisation côté API.
 */
export interface Role {
  id: string
  organizationId: string | null
  code: string
  name: string
  scope: 'platform' | 'organization' | null
  isSystem: boolean
  status: string
  /** Chargé par `index` et `show` ; absent des réponses qui n'ont pas fait le `with`. */
  permissions?: Permission[]
}

export const ROLE_STATUSES = ['active', 'inactive'] as const

/**
 * Un rôle est-il modifiable depuis l'administration d'un organisme ?
 *
 * Les mêmes trois conditions que `RolePolicy` côté backend. Les répéter ici
 * n'est pas une duplication de la sécurité — c'est éviter de proposer un
 * bouton qui mènerait à un refus.
 */
export function isEditableRole(role: Role): boolean {
  return !role.isSystem && role.scope !== 'platform'
}

/**
 * Le **menu** d'un rôle se règle-t-il ?
 *
 * Distinct de `isEditableRole`, et volontairement plus large : un rôle système
 * ne se modifie pas — il porte toutes les permissions, et les toucher ouvrirait
 * une voie d'élévation — mais son menu ne porte rien de tel. L'interdire
 * privait l'administrateur du seul menu qu'il voit lui-même, pour une raison
 * qui ne le concernait pas.
 *
 * Reste exclu le rôle de portée plateforme : il n'appartient pas à
 * l'organisation. Les mêmes conditions que `RolePolicy::updateMenu`, répétées
 * ici pour ne pas proposer un bouton qui mènerait à un refus.
 */
export function isMenuEditableRole(role: Role): boolean {
  return role.scope !== 'platform'
}

/**
 * Le **tableau de bord** d'un rôle se règle-t-il ?
 *
 * La même règle que pour son menu, et pour la même raison : un rôle système ne
 * se modifie pas, mais composer son tableau de bord n'accorde aucun droit —
 * chaque widget reste soumis à sa propre permission. L'interdire aurait privé
 * l'administrateur du seul tableau de bord qu'il voit lui-même.
 *
 * Reste exclu le rôle de portée plateforme : il n'appartient pas à
 * l'organisation. Les mêmes conditions que `RolePolicy::configureDashboard`,
 * répétées ici pour ne pas proposer un réglage qui mènerait à un refus.
 */
export function isDashboardEditableRole(role: Role): boolean {
  return role.scope !== 'platform'
}

export interface RoleFilters {
  page: number
  perPage: number
}

/**
 * Regroupe les permissions par section de menu.
 *
 * Le référentiel de permissions est versionné avec le code, pas modifiable à
 * l'exécution : `GET /permissions` ne fait que le lire. Ce qui se pilote depuis
 * l'interface, c'est l'association rôle → permissions.
 *
 * Une permission sans section — le cas ne devrait pas se produire, un test
 * backend l'interdit — tombe dans « Autres » plutôt que de disparaître : une
 * permission invisible serait impossible à accorder, et personne ne saurait
 * pourquoi.
 *
 * À l'intérieur d'une section, l'ordre suit le module puis le libellé : les
 * permissions d'un même sujet restent voisines.
 */
export function groupPermissionsBySection(permissions: Permission[]): [string, Permission[]][] {
  const groups = new Map<string, Permission[]>()

  for (const permission of permissions) {
    const section = permission.menuSection ?? 'other'
    const bucket = groups.get(section)

    if (bucket) {
      bucket.push(permission)
    } else {
      groups.set(section, [permission])
    }
  }

  for (const bucket of groups.values()) {
    bucket.sort(
      (a, b) => a.module.localeCompare(b.module) || a.name.localeCompare(b.name),
    )
  }

  const rank = (section: string) => {
    const index = MENU_SECTION_ORDER.indexOf(section as (typeof MENU_SECTION_ORDER)[number])

    return index === -1 ? MENU_SECTION_ORDER.length : index
  }

  return [...groups.entries()].sort(([a], [b]) => rank(a) - rank(b) || a.localeCompare(b))
}
