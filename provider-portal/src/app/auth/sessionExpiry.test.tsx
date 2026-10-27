import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'

import { session } from '@/shared/api/session'
import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

/**
 * Un jeton échu (401) ou un accès coupé (403 `provider_access_revoked`)
 * abandonne la session : jeton effacé, cache vidé, retour à la connexion,
 * sans laisser l'écran précédent ni ses données affichés.
 */
describe('fin de session', () => {
  it('abandonne une session échue et revient à la connexion', async () => {
    server.use(http.get(`${API}/dashboard`, () => HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 })))

    renderApp('/dashboard', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByRole('heading', { name: /Connexion/ })).toBeInTheDocument()
    expect(session.getToken()).toBeNull()
  })

  it('abandonne la session quand le transporteur coupe l’accès', async () => {
    server.use(
      http.get(`${API}/dashboard`, () =>
        HttpResponse.json({ message: 'Votre accès au portail fournisseur est fermé.', code: 'provider_access_revoked' }, { status: 403 }),
      ),
    )

    renderApp('/dashboard', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByRole('heading', { name: /Connexion/ })).toBeInTheDocument()
    expect(session.getToken()).toBeNull()
    expect(screen.queryByText('Transports Alpha')).not.toBeInTheDocument()
  })

  it('ne met jamais le jeton dans l’adresse', () => {
    renderApp('/dashboard', makeContext(ADMIN_PERMISSIONS))

    expect(window.location.href).not.toContain('test-token')
  })
})
