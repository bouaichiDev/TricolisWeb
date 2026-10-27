import { QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { MemoryRouter } from 'react-router-dom'

import { AuthProvider } from '@/app/auth/AuthProvider'
import { createQueryClient } from '@/app/queryClient'
import { AppRouter } from '@/app/router/AppRouter'
import { session } from '@/shared/api/session'
import type { CustomerContext } from '@/shared/api/types'

import { API, server } from './server'

/**
 * Monte le portail complet sur une route donnée.
 *
 * Avec `context`, une session est ouverte : le jeton est posé et `/context`
 * répond ce contexte, comme le ferait le backend.
 */
export function renderApp(route: string, context?: CustomerContext) {
  if (context) {
    session.setToken('test-token')
    server.use(http.get(`${API}/context`, () => HttpResponse.json({ data: context, meta: {} })))
  }

  return render(
    <QueryClientProvider client={createQueryClient()}>
      <MemoryRouter initialEntries={[route]}>
        <AuthProvider>
          <AppRouter />
        </AuthProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}
