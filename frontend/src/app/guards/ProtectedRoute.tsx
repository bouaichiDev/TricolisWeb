import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'

import { useAuth } from '@/shared/hooks/useAuth'
import { usePermissions } from '@/shared/hooks/usePermission'
import { FullPageLoader } from '@/shared/components/feedback/FullPageLoader'

interface ProtectedRouteProps {
  children: ReactNode
  /** Permission exigée pour atteindre la route. */
  permission?: string | string[]
  requireAll?: boolean
}

/**
 * Barrière de route : authentification, puis permission.
 *
 * L'ordre compte. Un visiteur non authentifié est renvoyé vers la connexion en
 * conservant l'adresse demandée ; un utilisateur connecté mais sans le droit
 * voit une page d'accès refusé, pas la page de connexion — le renvoyer là
 * laisserait croire que sa session a expiré.
 */
export function ProtectedRoute({ children, permission, requireAll = false }: ProtectedRouteProps) {
  const { isAuthenticated, isLoading } = useAuth()
  const { has, hasAny, hasAll } = usePermissions()
  const location = useLocation()

  if (isLoading) return <FullPageLoader />

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />
  }

  if (permission !== undefined) {
    const required = Array.isArray(permission) ? permission : [permission]
    const allowed =
      required.length === 1 ? has(required[0]) : requireAll ? hasAll(required) : hasAny(required)

    if (!allowed) return <Navigate to="/forbidden" replace />
  }

  return <>{children}</>
}
