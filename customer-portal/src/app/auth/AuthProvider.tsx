import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'

import { authApi, type LoginPayload } from '@/modules/auth/api/authApi'
import { session } from '@/shared/api/session'

import { AuthContext, CONTEXT_QUERY_KEY, type AuthState } from './AuthContext'

/**
 * Source unique de l'identité, du client courant et des permissions.
 *
 * Tant qu'un jeton existe, `/context` est chargé ; c'est le backend qui dit
 * pour quel client on travaille et avec quels droits. Le portail ne choisit
 * rien lui-même : il n'envoie aucun identifiant de client.
 *
 * Quand le client HTTP abandonne la session (401, accès révoqué), l'état est
 * remis à zéro ici, et les données en cache disparaissent avec lui.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [token, setToken] = useState<string | null>(() => session.getToken())

  useEffect(
    () =>
      session.onClear(() => {
        setToken(null)
        queryClient.clear()
      }),
    [queryClient],
  )

  const contextQuery = useQuery({
    queryKey: CONTEXT_QUERY_KEY,
    queryFn: authApi.context,
    enabled: token !== null,
    staleTime: 5 * 60 * 1000,
  })

  const login = useCallback(
    async (payload: LoginPayload) => {
      const result = await authApi.login(payload)

      queryClient.clear()
      session.setToken(result.token)
      queryClient.setQueryData(CONTEXT_QUERY_KEY, result.context)
      setToken(result.token)
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // Jeton déjà expiré ou révoqué : la session locale part quand même.
    }

    session.clear()
  }, [])

  const refresh = useCallback(async () => {
    await queryClient.invalidateQueries({ queryKey: CONTEXT_QUERY_KEY })
  }, [queryClient])

  const context = token === null ? null : (contextQuery.data ?? null)

  const value = useMemo<AuthState>(
    () => ({
      context,
      permissions: new Set(context?.permissions ?? []),
      isAuthenticated: context !== null,
      isLoading: token !== null && contextQuery.isPending,
      loadError: token === null ? null : contextQuery.error,
      login,
      logout,
      refresh,
    }),
    [context, token, contextQuery.isPending, contextQuery.error, login, logout, refresh],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
