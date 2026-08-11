import { useQuery, useQueryClient } from '@tanstack/react-query'
import { createContext, useCallback, useMemo, useState, type ReactNode } from 'react'

import { authApi } from '@/modules/auth/api/auth.api'
import { ApiError } from '@/shared/api/client'
import { session } from '@/shared/api/session'
import type { AuthContextValue, AuthMembership } from '@/shared/types/auth'

export const AuthContext = createContext<AuthContextValue | null>(null)

const ME_QUERY_KEY = ['auth', 'me'] as const

/**
 * Source unique de l'identité et des habilitations.
 *
 * Trois responsabilités, et pas une de plus : charger `/auth/me` tant qu'un
 * jeton existe, choisir l'organisation active, et exposer les permissions de
 * l'appartenance correspondante.
 *
 * Le choix de l'organisation obéit à un ordre précis : celle enregistrée dans
 * la session si elle est toujours accessible, sinon l'appartenance marquée
 * principale, sinon la première. Sans ce repli, un utilisateur retiré d'une
 * organisation resterait bloqué sur un identifiant que l'API refuse.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [token, setToken] = useState<string | null>(() => session.getToken())
  const [organizationId, setOrganizationId] = useState<string | null>(() =>
    session.getOrganizationId(),
  )

  const meQuery = useQuery({
    queryKey: ME_QUERY_KEY,
    queryFn: authApi.me,
    enabled: token !== null,
    retry: (failureCount, error) =>
      error instanceof ApiError && error.isUnauthenticated ? false : failureCount < 2,
    staleTime: 5 * 60 * 1000,
  })

  const user = meQuery.data?.user ?? null
  const memberships = useMemo<AuthMembership[]>(() => user?.organizations ?? [], [user])

  const membership = useMemo(() => {
    if (memberships.length === 0) return null

    const stored = memberships.find((item) => item.id === organizationId)
    if (stored) return stored

    return memberships.find((item) => item.isPrimary) ?? memberships[0]
  }, [memberships, organizationId])

  // L'en-tête envoyé par le client HTTP doit suivre l'appartenance retenue,
  // y compris quand celle-ci vient du repli plutôt que du stockage.
  if (membership && membership.id !== session.getOrganizationId()) {
    session.setOrganizationId(membership.id)
  }

  const switchOrganization = useCallback(
    (nextId: string) => {
      if (nextId === session.getOrganizationId()) return

      session.setOrganizationId(nextId)
      setOrganizationId(nextId)

      // Toutes les données en cache appartiennent à l'organisation précédente.
      // Les invalider en bloc est le seul moyen sûr : une invalidation
      // sélective laisserait passer ce qu'on aurait oublié de lister.
      void queryClient.invalidateQueries()
    },
    [queryClient],
  )

  const login = useCallback(
    async (email: string, password: string) => {
      const payload = await authApi.login(email, password)

      session.setToken(payload.token)
      setToken(payload.token)

      const preferred =
        payload.user.organizations.find((item) => item.isPrimary) ??
        payload.user.organizations[0] ??
        null

      session.setOrganizationId(preferred?.id ?? null)
      setOrganizationId(preferred?.id ?? null)

      queryClient.setQueryData(ME_QUERY_KEY, { user: payload.user })
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // Le serveur a pu refuser un jeton déjà expiré : la session locale doit
      // disparaître dans tous les cas, sinon l'utilisateur reste coincé.
    }

    session.clear()
    setToken(null)
    setOrganizationId(null)
    queryClient.clear()
  }, [queryClient])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      memberships,
      membership,
      organizationId: membership?.id ?? null,
      roles: membership?.roles ?? [],
      permissions: membership?.permissions.map((permission) => permission.code) ?? [],
      agencies: membership?.agencies ?? [],
      isOwner: membership?.isOwner ?? false,
      isAuthenticated: token !== null && user !== null,
      isLoading: token !== null && meQuery.isPending,
      login,
      logout,
      switchOrganization,
    }),
    [user, memberships, membership, token, meQuery.isPending, login, logout, switchOrganization],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
