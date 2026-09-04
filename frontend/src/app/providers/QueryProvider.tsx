import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'

import { ApiError } from '@/shared/api/client'

/**
 * Client TanStack Query.
 *
 * Deux réglages méritent une justification :
 *
 * - **aucune nouvelle tentative sur 401, 403, 404 et 422.** Réessayer un refus
 *   d'autorisation ne le transformera pas en accord ; cela ne fait qu'ajouter
 *   du bruit dans les journaux du serveur et retarder le message d'erreur.
 * - **pas de rechargement au retour sur l'onglet.** Sur un back-office, les
 *   données ne changent pas assez vite pour justifier une requête à chaque
 *   changement de fenêtre ; le bouton de rafraîchissement reste disponible.
 */
function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30 * 1000,
        refetchOnWindowFocus: false,
        retry: (failureCount, error) => {
          if (error instanceof ApiError) {
            const definitive = [401, 403, 404, 422].includes(error.status)
            if (definitive) return false
          }

          return failureCount < 2
        },
      },
      mutations: {
        retry: false,
      },
    },
  })
}

export function QueryProvider({ children }: { children: ReactNode }) {
  const [queryClient] = useState(createQueryClient)

  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
}
