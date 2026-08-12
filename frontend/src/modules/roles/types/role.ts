/** Permission — champs relevés sur `PermissionResource`. */
export interface Permission {
  id: string
  code: string
  name: string
  module: string
  action: string
}

/** Rôle — champs relevés sur `RoleResource`. */
export interface Role {
  id: string
  organizationId: string
  code: string
  name: string
  scope: string | null
  isSystem: boolean
  status: string
  /** Chargé par `index` et `show` ; absent des réponses qui n'ont pas fait le `with`. */
  permissions?: Permission[]
}

export const ROLE_STATUSES = ['active', 'inactive'] as const

export interface RoleFilters {
  page: number
  perPage: number
}

/**
 * Le référentiel de permissions est versionné avec le code, pas modifiable à
 * l'exécution : `GET /permissions` ne fait que le lire. Ce qui se pilote depuis
 * l'interface, c'est l'association rôle → permissions.
 */
export function groupPermissionsByModule(permissions: Permission[]): [string, Permission[]][] {
  const groups = new Map<string, Permission[]>()

  for (const permission of permissions) {
    const bucket = groups.get(permission.module)
    if (bucket) {
      bucket.push(permission)
    } else {
      groups.set(permission.module, [permission])
    }
  }

  return [...groups.entries()].sort(([a], [b]) => a.localeCompare(b))
}
