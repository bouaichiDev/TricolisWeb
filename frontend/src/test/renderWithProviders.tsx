import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, type RenderResult } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'

import { makeMembership, makeUser } from './fixtures'
import { AuthContext } from '@/app/providers/AuthProvider'
import { Toaster } from '@/shared/components/ui/sonner'
import type { AuthContextValue, AuthMembership } from '@/shared/types/auth'

interface Options {
  /** Appartenance active ; ses permissions sont celles que verront les gardes. */
  membership?: AuthMembership | null
  /** Appartenances disponibles ; par défaut, la seule appartenance active. */
  memberships?: AuthMembership[]
  route?: string
  /**
   * Motif de route sous lequel monter le composant.
   *
   * Nécessaire dès qu'une page lit `useParams` : sans motif, `/orders/:id` ne
   * serait jamais apparié et l'identifiant resterait indéfini.
   */
  routePath?: string
  isAuthenticated?: boolean
  isLoading?: boolean
  /** Espion sur le changement d'organisation. */
  onSwitchOrganization?: (organizationId: string) => void
}

/**
 * Monte un composant dans les mêmes fournisseurs qu'en production.
 *
 * L'authentification est injectée directement dans le contexte plutôt que
 * simulée via `/auth/me` : les tests portent sur ce que l'interface fait d'un
 * jeu de permissions donné, pas sur la façon dont il est chargé — ce dernier
 * point a son propre test.
 *
 * Les nouvelles tentatives sont désactivées : un test qui échoue doit échouer
 * tout de suite, pas après trois requêtes.
 */
export function renderWithProviders(ui: ReactElement, options: Options = {}): RenderResult {
  const {
    membership = makeMembership(),
    route = '/',
    routePath,
    isAuthenticated = true,
    isLoading = false,
    onSwitchOrganization = () => {},
  } = options

  const memberships = options.memberships ?? (membership ? [membership] : [])

  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false, staleTime: 0, gcTime: 0 },
      mutations: { retry: false },
    },
  })

  const auth: AuthContextValue = {
    user: makeUser(memberships),
    memberships,
    membership,
    organizationId: membership?.id ?? null,
    roles: membership?.roles ?? [],
    permissions: (membership?.permissions ?? []).map((permission) => permission.code),
    agencies: membership?.agencies ?? [],
    isOwner: membership?.isOwner ?? false,
    // Lu sur l'ensemble des appartenances, comme en production : un rôle
    // plateforme n'est pas rattaché à l'organisation active.
    isPlatformAdmin: memberships.some((item) => item.roles.some((role) => role.scope === 'platform')),
    isAuthenticated,
    isLoading,
    login: async () => {},
    logout: async () => {},
    switchOrganization: onSwitchOrganization,
  }

  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <AuthContext.Provider value={auth}>
          <MemoryRouter initialEntries={[route]}>
            {routePath === undefined ? (
              children
            ) : (
              <Routes>
                <Route path={routePath} element={children} />
              </Routes>
            )}
          </MemoryRouter>
          {/* Monté comme en production : sans lui, les messages de refus et de
              confirmation ne s'afficheraient nulle part, et les tests qui les
              vérifient passeraient à côté d'un écran muet. */}
          <Toaster />
        </AuthContext.Provider>
      </QueryClientProvider>
    )
  }

  return render(ui, { wrapper: Wrapper })
}
