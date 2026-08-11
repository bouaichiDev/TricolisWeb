import { useContext } from 'react'

import { AuthContext } from '@/app/providers/AuthProvider'
import type { AuthContextValue } from '@/shared/types/auth'

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)

  if (context === null) {
    throw new Error('useAuth doit être appelé à l’intérieur d’un AuthProvider.')
  }

  return context
}
