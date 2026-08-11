import type { ReactNode } from 'react'

import { usePermissions } from '@/shared/hooks/usePermission'

interface PermissionGuardProps {
  /** Permission requise, ou liste dont **une seule** suffit. */
  permission: string | string[]
  /** Exiger toutes les permissions de la liste plutôt qu'une seule. */
  requireAll?: boolean
  children: ReactNode
  /** Affiché à la place quand le droit manque. Rien par défaut. */
  fallback?: ReactNode
}

/**
 * Masque un élément d'interface quand la permission manque.
 *
 * Sert au confort, pas à la sécurité : cacher un bouton évite de proposer une
 * action qui échouerait, mais le backend refuse l'appel de toute façon.
 */
export function PermissionGuard({
  permission,
  requireAll = false,
  children,
  fallback = null,
}: PermissionGuardProps) {
  const { has, hasAny, hasAll } = usePermissions()

  const required = Array.isArray(permission) ? permission : [permission]
  const allowed =
    required.length === 1 ? has(required[0]) : requireAll ? hasAll(required) : hasAny(required)

  return <>{allowed ? children : fallback}</>
}
