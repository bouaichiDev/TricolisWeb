import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'

import { useAuth, usePermissions } from '@/app/auth/useAuth'
import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { ErrorState, FullPageLoader } from '@/shared/components/feedback/States'

interface ProtectedRouteProps {
  children: ReactNode
  /** Permission exigée ; toutes si plusieurs. */
  permission?: string | readonly string[]
}

/**
 * Barrière de route : session, puis permission.
 *
 * Sans session → connexion, en retenant l'adresse demandée. Connecté sans le
 * droit → « accès refusé », jamais la connexion : ce serait laisser croire
 * que la session a expiré.
 *
 * C'est un confort de navigation. Le backend revérifie chaque permission.
 */
export function ProtectedRoute({ children, permission }: ProtectedRouteProps) {
  const { isAuthenticated, isLoading, loadError, refresh } = useAuth()
  const { canAll } = usePermissions()
  const location = useLocation()

  if (isLoading) return <FullPageLoader />

  if (!isAuthenticated && loadError) {
    return <ErrorState message={errorMessage(loadError)} onRetry={() => void refresh()} />
  }

  if (!isAuthenticated) {
    return <Navigate to={paths.login} state={{ from: location }} replace />
  }

  if (permission !== undefined) {
    const required = typeof permission === 'string' ? [permission] : permission

    if (!canAll(required)) return <Navigate to={paths.accessDenied} replace />
  }

  return <>{children}</>
}

/** Pages publiques (connexion…) : une session ouverte renvoie au tableau de bord. */
export function PublicOnlyRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth()

  if (isLoading) return <FullPageLoader />
  if (isAuthenticated) return <Navigate to={paths.dashboard} replace />

  return <>{children}</>
}
