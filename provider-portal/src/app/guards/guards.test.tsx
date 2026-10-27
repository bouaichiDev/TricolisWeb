import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

describe('gardes de route et menu', () => {
  it('renvoie un visiteur sans session vers la connexion', async () => {
    renderApp('/dashboard')

    expect(await screen.findByRole('heading', { name: /Connexion/ })).toBeInTheDocument()
  })

  it('n’affiche que les entrées que le serveur a ouvertes', async () => {
    server.use(http.get(`${API}/navigation`, () => HttpResponse.json({ data: [{ key: 'dashboard', label: 'Tableau de bord', path: '/dashboard' }], meta: {} })))
    renderApp('/dashboard', makeContext(['provider.portal.access', 'provider.dashboard.view']))

    const nav = await screen.findByRole('navigation', { name: 'Navigation principale' })

    expect(await within(nav).findByRole('link', { name: /Tableau de bord/ })).toBeInTheDocument()
    expect(within(nav).queryByText('Profil')).not.toBeInTheDocument()
  })

  it('ne propose jamais de gestion d’utilisateurs ni de rôles, même si on la lui sert', async () => {
    server.use(
      http.get(`${API}/navigation`, () =>
        HttpResponse.json({
          data: [
            { key: 'dashboard', label: 'Tableau de bord', path: '/dashboard' },
            { key: 'users', label: 'Utilisateurs', path: '/users' },
            { key: 'roles', label: 'Rôles', path: '/roles' },
          ],
          meta: {},
        }),
      ),
    )
    renderApp('/dashboard', makeContext(ADMIN_PERMISSIONS))

    const nav = await screen.findByRole('navigation', { name: 'Navigation principale' })

    expect(await within(nav).findByRole('link', { name: /Tableau de bord/ })).toBeInTheDocument()
    expect(within(nav).queryByText('Utilisateurs')).not.toBeInTheDocument()
    expect(within(nav).queryByText('Rôles')).not.toBeInTheDocument()
  })

  it('montre « accès refusé » quand la permission manque', async () => {
    renderApp('/profile', makeContext(['provider.portal.access', 'provider.dashboard.view']))

    expect(await screen.findByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
  })

  it('affiche le fournisseur courant dans le bandeau', async () => {
    renderApp('/dashboard', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByTestId('current-provider')).toHaveTextContent('Transports Alpha')
  })

  it('montre une page introuvable pour une adresse inconnue', async () => {
    renderApp('/users', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByRole('heading', { name: 'Page introuvable' })).toBeInTheDocument()
  })
})
