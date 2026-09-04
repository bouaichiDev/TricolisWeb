import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'

import { ProtectedRoute } from './ProtectedRoute'
import { platformMembership, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'

/**
 * Les écrans d'organisme ne concernent pas la plateforme.
 *
 * Un compte plateforme est techniquement membre d'une organisation — le schéma
 * l'impose pour porter un rôle — et en détient donc les permissions. Sans ce
 * garde, il atteignait par l'URL les clients de cette organisation, alors que
 * son menu ne les propose pas : l'interface se contredisait.
 */
function routes() {
  return (
    <Routes>
      <Route path="/organizations" element={<h1>Organisations</h1>} />
      <Route
        path="/customers"
        element={
          <ProtectedRoute permission="customers.view" organizationOnly>
            <h1>Clients</h1>
          </ProtectedRoute>
        }
      />
    </Routes>
  )
}

describe('ProtectedRoute, écrans d’organisme', () => {
  it('renvoie un compte plateforme vers son propre périmètre', () => {
    renderWithProviders(routes(), {
      route: '/customers',
      membership: platformMembership({
        permissions: [{ id: 'p1', code: 'customers.view' }],
      }),
    })

    expect(screen.getByRole('heading', { name: 'Organisations' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Clients' })).not.toBeInTheDocument()
  })

  /**
   * Un renvoi, pas un refus : le compte plateforme n'a rien fait d'interdit,
   * cette page ne le concerne simplement pas. Afficher « Accès refusé »
   * suggérerait une permission manquante, ce qui serait faux.
   */
  it('ne présente pas ce renvoi comme un refus', () => {
    renderWithProviders(routes(), {
      route: '/customers',
      membership: platformMembership({
        permissions: [{ id: 'p1', code: 'customers.view' }],
      }),
    })

    expect(screen.queryByText(/refusé/i)).not.toBeInTheDocument()
  })

  it('laisse passer un compte d’organisme', () => {
    renderWithProviders(routes(), {
      route: '/customers',
      membership: withPermissions(['customers.view']),
    })

    expect(screen.getByRole('heading', { name: 'Clients' })).toBeInTheDocument()
  })
})
