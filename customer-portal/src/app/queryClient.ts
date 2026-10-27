import { QueryClient } from '@tanstack/react-query'

import { ApiError } from '@/shared/api/errors'

/** Aucune nouvelle tentative sur un refus : le rejouer ne le changera pas. */
export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        refetchOnWindowFocus: false,
        retry: (failureCount, error) =>
          error instanceof ApiError && error.status < 500 ? false : failureCount < 2,
      },
      mutations: { retry: false },
    },
  })
}
