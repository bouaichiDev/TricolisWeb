import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'

import { RoleAssignment } from './RoleAssignment'
import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const local = {
  id: 'r1',
  organizationId: 'ORG-1',
  code: 'operateur',
  name: 'Opérateur',
  scope: 'organization',
  isSystem: false,
  status: 'active',
}

const systemRole = { ...local, id: 'r2', code: 'admin', name: 'Administrateur', isSystem: true }
const platformRole = {
  ...local,
  id: 'r3',
  organizationId: null,
  code: 'superadmin',
  name: 'Administrateur plateforme',
  scope: 'platform',
  isSystem: true,
}

function rolesHandler(rows: unknown[]) {
  return http.get(`${API}/roles`, () => HttpResponse.json(paginated(rows)))
}

/**
 * Attribution des rôles à un membre.
 *
 * Un rôle système porte l'intégralité des permissions de son organisation :
 * l'attribuer transmettrait des droits que l'attribuant ne détient pas
 * nécessairement. L'API le refuse par un 422 ; ne pas le proposer évite d'y
 * conduire.
 */
describe('RoleAssignment', () => {
  it('propose les rôles locaux ordinaires', async () => {
    server.use(rolesHandler([local]))
    renderWithProviders(<RoleAssignment selected={[]} onChange={vi.fn()} />, {
      membership: withPermissions(['users.assign_roles', 'roles.view']),
    })

    expect(await screen.findByLabelText(/Opérateur/)).toBeInTheDocument()
  })

  it('écarte les rôles système', async () => {
    server.use(rolesHandler([local, systemRole]))
    renderWithProviders(<RoleAssignment selected={[]} onChange={vi.fn()} />, {
      membership: withPermissions(['users.assign_roles', 'roles.view']),
    })

    await screen.findByLabelText(/Opérateur/)
    expect(screen.queryByLabelText(/Administrateur$/)).not.toBeInTheDocument()
  })

  it('écarte les rôles plateforme', async () => {
    server.use(rolesHandler([local, platformRole]))
    renderWithProviders(<RoleAssignment selected={[]} onChange={vi.fn()} />, {
      membership: withPermissions(['users.assign_roles', 'roles.view']),
    })

    await screen.findByLabelText(/Opérateur/)
    expect(screen.queryByLabelText(/Administrateur plateforme/)).not.toBeInTheDocument()
  })

  it('annonce l’absence de rôle attribuable quand seuls des rôles verrouillés existent', async () => {
    server.use(rolesHandler([systemRole, platformRole]))
    renderWithProviders(<RoleAssignment selected={[]} onChange={vi.fn()} />, {
      membership: withPermissions(['users.assign_roles', 'roles.view']),
    })

    expect(await screen.findByText('Aucun rôle disponible')).toBeInTheDocument()
  })
})
