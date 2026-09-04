import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'

import { PermissionGuard } from './PermissionGuard'
import { ProtectedRoute } from './ProtectedRoute'
import { platformMembership, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'

/**
 * Frontière plateforme / organisme dans l'interface.
 *
 * Une permission ne suffit pas à trancher : un administrateur d'organisme
 * détient légitimement `organizations.view` pour consulter la sienne. C'est la
 * **portée** du rôle qui distingue les deux niveaux, et ces tests vérifient que
 * l'interface s'y tient.
 *
 * Ce qui est vérifié ici n'est pas la sécurité — le backend refuse de toute
 * façon — mais le fait de ne pas proposer une action vouée à l'échec.
 */
const localOwner = withPermissions(['organizations.view', 'organizations.create'], {
  isOwner: true,
})

const platform = platformMembership({
  permissions: [
    { id: 'p1', code: 'organizations.view' },
    { id: 'p2', code: 'organizations.create' },
  ],
})

describe('PermissionGuard, portée plateforme', () => {
  it('montre l’action à un administrateur de plateforme', () => {
    renderWithProviders(
      <PermissionGuard permission="organizations.create" platformOnly>
        <button>Nouvelle organisation</button>
      </PermissionGuard>,
      { membership: platform },
    )

    expect(screen.getByRole('button', { name: 'Nouvelle organisation' })).toBeInTheDocument()
  })

  /**
   * Le cas qui motive toute la correction : le propriétaire détient la
   * permission — elle lui était accordée par le rôle `admin` semé — mais n'a pas
   * la portée. Le bouton disparaît.
   */
  it('masque l’action à un propriétaire d’organisme malgré la permission', () => {
    renderWithProviders(
      <PermissionGuard permission="organizations.create" platformOnly>
        <button>Nouvelle organisation</button>
      </PermissionGuard>,
      { membership: localOwner },
    )

    expect(screen.queryByRole('button', { name: 'Nouvelle organisation' })).not.toBeInTheDocument()
  })

  it('n’accorde aucune portée à un rôle simplement nommé SuperAdmin', () => {
    const impostor = withPermissions(['organizations.create'], {
      roles: [
        {
          id: 'r1',
          code: 'SUPER_ADMIN',
          name: 'SuperAdmin',
          scope: 'organization',
          isSystem: false,
        },
      ],
    })

    renderWithProviders(
      <PermissionGuard permission="organizations.create" platformOnly>
        <button>Nouvelle organisation</button>
      </PermissionGuard>,
      { membership: impostor },
    )

    expect(screen.queryByRole('button', { name: 'Nouvelle organisation' })).not.toBeInTheDocument()
  })
})

describe('ProtectedRoute, portée plateforme', () => {
  function routes() {
    return (
      <Routes>
        <Route path="/forbidden" element={<h1>Accès refusé</h1>} />
        <Route
          path="/organizations/create"
          element={
            <ProtectedRoute permission="organizations.create" platformOnly>
              <h1>Nouvelle organisation</h1>
            </ProtectedRoute>
          }
        />
      </Routes>
    )
  }

  /**
   * Masquer le bouton ne protège pas l'adresse : sans ce contrôle, saisir
   * `/organizations/create` ouvrait le formulaire, pour un refus au moment de
   * l'envoi.
   */
  it('refuse l’accès direct à /organizations/create pour un administrateur local', () => {
    renderWithProviders(routes(), { route: '/organizations/create', membership: localOwner })

    expect(screen.getByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Nouvelle organisation' })).not.toBeInTheDocument()
  })

  it('laisse passer un administrateur de plateforme', () => {
    renderWithProviders(routes(), { route: '/organizations/create', membership: platform })

    expect(screen.getByRole('heading', { name: 'Nouvelle organisation' })).toBeInTheDocument()
  })
})
