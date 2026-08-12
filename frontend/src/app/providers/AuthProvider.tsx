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

  /**
   * Autorité plateforme.
   *
   * Lue sur l'ensemble des appartenances : un rôle plateforme n'a pas
   * d'organisation et se rattache à l'une quelconque des adhésions du compte.
   * La restreindre à l'organisation active la ferait disparaître dès que
   * l'utilisateur change d'organisation.
   *
   * Ce booléen ne protège rien : il évite de proposer une action que l'API
   * refuserait. Le backend reste seul juge.
   */
  const isPlatformAdmin = useMemo(
    () => memberships.some((item) => item.roles.some((role) => role.scope === 'platform')),
    [memberships],
  )

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

      /**
       * Toutes les données en cache appartiennent à l'organisation précédente.
       *
       * Elles sont **retirées**, pas seulement invalidées. Une invalidation les
       * marque périmées mais les laisse affichées pendant le rechargement — et
       * `placeholderData: (previous) => previous`, que portent toutes les
       * listes, les y maintiendrait activement. L'utilisateur verrait les
       * clients de l'organisation qu'il vient de quitter.
       *
       * L'identité est conservée puis invalidée : la vider déconnecterait
       * l'utilisateur le temps du rechargement. Ses rôles et ses permissions en
       * dépendent — ils sont portés par l'appartenance, pas par le compte — et
       * arrivent donc avec la réponse de `/auth/me`.
       */
      queryClient.removeQueries({
        predicate: (query) => query.queryKey[0] !== ME_QUERY_KEY[0],
      })
      void queryClient.invalidateQueries({ queryKey: ME_QUERY_KEY })
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
      isPlatformAdmin,
      isAuthenticated: token !== null && user !== null,
      isLoading: token !== null && meQuery.isPending,
      login,
      logout,
      switchOrganization,
    }),
    [
      user,
      memberships,
      membership,
      isPlatformAdmin,
      token,
      meQuery.isPending,
      login,
      logout,
      switchOrganization,
    ],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
