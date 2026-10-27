import { QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import type { ReactElement } from 'react'
import { MemoryRouter } from 'react-router-dom'

import { AuthProvider } from '@/app/auth/AuthProvider'
import { createQueryClient } from '@/app/queryClient'
import { session } from '@/shared/api/session'

import { makeContext } from './fixtures'
import { API, server } from './server'

/**
 * Monte un composant isolé avec une session ouverte portant ces permissions :
 * pour tester un composant partagé qui lit les droits (`Can`, `RowActions`).
 */
export function renderWithAuth(ui: ReactElement, permissions: string[] = []) {
  session.setToken('test-token')
  server.use(http.get(`${API}/context`, () => HttpResponse.json({ data: makeContext(permissions), meta: {} })))

  return render(
    <QueryClientProvider client={createQueryClient()}>
      <MemoryRouter>
        <AuthProvider>{ui}</AuthProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}
