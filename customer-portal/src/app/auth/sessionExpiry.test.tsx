import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'

import { session } from '@/shared/api/session'
import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

/**
 * Phase 7 : un jeton du portail expire. Le premier 401 abandonne la session —
 * jeton effacé, retour à la connexion — sans laisser l'écran précédent ni ses
 * données affichés.
 */
describe('session expiry', () => {
  it('drops an expired session and returns to the login page', async () => {
    server.use(http.get(`${API}/orders`, () => HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 })))

    renderApp('/orders', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByRole('heading', { name: /Connexion/ })).toBeInTheDocument()
    expect(session.getToken()).toBeNull()
  })

  it('never keeps the token in the address bar', () => {
    renderApp('/orders', makeContext(ADMIN_PERMISSIONS))

    expect(window.location.href).not.toContain('test-token')
  })
})
