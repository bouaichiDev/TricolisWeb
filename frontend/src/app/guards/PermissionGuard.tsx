import type { ReactNode } from 'react'

import { usePermissions } from '@/shared/hooks/usePermission'

interface PermissionGuardProps {
  /** Permission requise, ou liste dont **une seule** suffit. */
  permission?: string | string[]
  /** Exiger toutes les permissions de la liste plutôt qu'une seule. */
  requireAll?: boolean
  /**
   * Exiger l'autorité plateforme, en plus de la permission éventuelle.
   *
   * Nécessaire parce qu'une permission seule ne suffit pas à distinguer les deux
   * niveaux : `organizations.view` est légitime pour un administrateur
   * d'organisme, qui ne doit pourtant pas voir la liste globale. Le drapeau
   * exprime ce que la permission ne peut pas dire.
   */
  platformOnly?: boolean
  children: ReactNode
  /** Affiché à la place quand le droit manque. Rien par défaut. */
  fallback?: ReactNode
}

/**
 * Masque un élément d'interface quand le droit manque.
 *
 * Sert au confort, pas à la sécurité : cacher un bouton évite de proposer une
 * action qui échouerait, mais le backend refuse l'appel de toute façon — et
 * c'est là que se trouve la protection réelle.
 */
export function PermissionGuard({
  permission,
  requireAll = false,
  platformOnly = false,
  children,
  fallback = null,
}: PermissionGuardProps) {
  const { has, hasAny, hasAll, isPlatformAdmin } = usePermissions()

  if (platformOnly && !isPlatformAdmin) {
    return <>{fallback}</>
  }

  if (permission === undefined) {
    return <>{children}</>
  }

  const required = Array.isArray(permission) ? permission : [permission]
  const allowed =
    required.length === 1 ? has(required[0]) : requireAll ? hasAll(required) : hasAny(required)

  return <>{allowed ? children : fallback}</>
}
