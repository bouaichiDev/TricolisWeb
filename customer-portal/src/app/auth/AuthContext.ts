import { createContext } from 'react'

import type { LoginPayload } from '@/modules/auth/api/authApi'
import type { CustomerContext } from '@/shared/api/types'

export interface AuthState {
  /** Contexte du client courant, tel que le backend le calcule. */
  context: CustomerContext | null
  permissions: ReadonlySet<string>
  isAuthenticated: boolean
  isLoading: boolean
  /** Échec du chargement du contexte hors session expirée (serveur injoignable…). */
  loadError: unknown
  login: (payload: LoginPayload) => Promise<void>
  logout: () => Promise<void>
  /** Recharge le contexte après une modification de profil. */
  refresh: () => Promise<void>
}

export const CONTEXT_QUERY_KEY = ['customer-context'] as const

export const AuthContext = createContext<AuthState | null>(null)
