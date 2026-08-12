import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'

import { FullPageLoader } from '@/shared/components/feedback/FullPageLoader'
import { useAuth } from '@/shared/hooks/useAuth'
import { usePermissions } from '@/shared/hooks/usePermission'

interface ProtectedRouteProps {
  children: ReactNode
  /** Permission exigée pour atteindre la route. */
  permission?: string | string[]
  requireAll?: boolean
  /**
   * Route réservée à l'administration de la plateforme.
   *
   * Sans ce contrôle, masquer le bouton suffisait à croire la route protégée :
   * il restait possible de saisir `/organizations/create` dans la barre
   * d'adresse. Le backend refusait déjà l'appel, mais l'utilisateur atteignait
   * un formulaire voué à l'échec.
   */
  platformOnly?: boolean
}

/**
 * Barrière de route : authentification, puis portée, puis permission.
 *
 * L'ordre compte. Un visiteur non authentifié est renvoyé vers la connexion en
 * conservant l'adresse demandée ; un utilisateur connecté mais sans le droit
 * voit une page d'accès refusé, pas la page de connexion — le renvoyer là
 * laisserait croire que sa session a expiré.
 */
export function ProtectedRoute({
  children,
  permission,
  requireAll = false,
  platformOnly = false,
}: ProtectedRouteProps) {
  const { isAuthenticated, isLoading } = useAuth()
  const { has, hasAny, hasAll, isPlatformAdmin } = usePermissions()
  const location = useLocation()

  if (isLoading) return <FullPageLoader />

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />
  }

  if (platformOnly && !isPlatformAdmin) {
    return <Navigate to="/forbidden" replace />
  }

  if (permission !== undefined) {
    const required = Array.isArray(permission) ? permission : [permission]
    const allowed =
      required.length === 1 ? has(required[0]) : requireAll ? hasAll(required) : hasAny(required)

    if (!allowed) return <Navigate to="/forbidden" replace />
  }

  return <>{children}</>
}
