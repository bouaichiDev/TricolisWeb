import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'

import { ProtectedRoute } from './ProtectedRoute'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'

/** Trois destinations possibles, pour distinguer laquelle a été atteinte. */
function routes(guarded: React.ReactNode) {
  return (
    <Routes>
      <Route path="/login" element={<h1>Connexion</h1>} />
      <Route path="/forbidden" element={<h1>Accès refusé</h1>} />
      <Route path="/customers" element={guarded} />
    </Routes>
  )
}

describe('ProtectedRoute', () => {
  it('affiche la page quand la session et la permission sont là', () => {
    renderWithProviders(
      routes(
        <ProtectedRoute permission="customers.view">
          <h1>Clients</h1>
        </ProtectedRoute>,
      ),
      { route: '/customers', membership: withPermissions(['customers.view']) },
    )

    expect(screen.getByRole('heading', { name: 'Clients' })).toBeInTheDocument()
  })

  it('renvoie vers la connexion quand la session manque', () => {
    renderWithProviders(
      routes(
        <ProtectedRoute permission="customers.view">
          <h1>Clients</h1>
        </ProtectedRoute>,
      ),
      { route: '/customers', isAuthenticated: false, membership: null },
    )

    expect(screen.getByRole('heading', { name: 'Connexion' })).toBeInTheDocument()
  })

  /**
   * Distinction volontaire : connecté mais sans droit ne doit **pas** renvoyer
   * vers la connexion, sans quoi l'utilisateur croirait sa session expirée et
   * se reconnecterait en boucle.
   */
  it('renvoie vers « accès refusé » quand la permission manque, pas vers la connexion', () => {
    renderWithProviders(
      routes(
        <ProtectedRoute permission="customers.view">
          <h1>Clients</h1>
        </ProtectedRoute>,
      ),
      { route: '/customers', membership: withPermissions(['dashboard.view']) },
    )

    expect(screen.getByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Connexion' })).not.toBeInTheDocument()
  })

  it('n’exige aucune permission quand la route n’en déclare pas', () => {
    renderWithProviders(
      routes(
        <ProtectedRoute>
          <h1>Clients</h1>
        </ProtectedRoute>,
      ),
      { route: '/customers', membership: withPermissions([]) },
    )

    expect(screen.getByRole('heading', { name: 'Clients' })).toBeInTheDocument()
  })
})
