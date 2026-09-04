import { BrowserRouter } from 'react-router-dom'

import { AuthProvider } from '@/app/providers/AuthProvider'
import { QueryProvider } from '@/app/providers/QueryProvider'
import { AppRouter } from '@/app/router/AppRouter'
import { Toaster } from '@/shared/components/ui/sonner'

/**
 * Racine de l'application.
 *
 * L'ordre des fournisseurs n'est pas arbitraire : `AuthProvider` interroge
 * TanStack Query pour charger la session, il doit donc se trouver a
 * l'interieur de `QueryProvider`. Le routeur vient en dernier, puisqu'il
 * consomme les deux.
 */
export function App() {
  return (
    <QueryProvider>
      <BrowserRouter>
        <AuthProvider>
          <AppRouter />
          <Toaster position="top-right" richColors closeButton />
        </AuthProvider>
      </BrowserRouter>
    </QueryProvider>
  )
}
