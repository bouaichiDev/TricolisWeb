import { useContext } from 'react'

import { AuthContext, type AuthState } from './AuthContext'

export function useAuth(): AuthState {
  const value = useContext(AuthContext)

  if (value === null) {
    throw new Error('useAuth doit être utilisé sous <AuthProvider>.')
  }

  return value
}

/**
 * Lecture des permissions effectives.
 *
 * Sert à masquer ce qui échouerait ; la protection réelle est côté backend,
 * qui revérifie chaque permission à chaque requête.
 */
export function usePermissions() {
  const { permissions } = useAuth()

  return {
    can: (permission: string) => permissions.has(permission),
    canAny: (list: readonly string[]) => list.some((permission) => permissions.has(permission)),
    canAll: (list: readonly string[]) => list.every((permission) => permissions.has(permission)),
  }
}
