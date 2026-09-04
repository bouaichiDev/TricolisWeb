import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'

import { RoleDetailPage } from './RoleDetailPage'
import { RoleEditPage } from './RoleEditPage'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const ROLE_ID = '01JQZ00000000000000ROLE1'

const localRole = {
  id: ROLE_ID,
  organizationId: 'ORG-1',
  code: 'operateur',
  name: 'Opérateur',
  scope: 'organization',
  isSystem: false,
  status: 'active',
  permissions: [],
}

const systemRole = { ...localRole, code: 'admin', name: 'Administrateur', isSystem: true }

function roleHandler(role: unknown) {
  return http.get(`${API}/roles/${ROLE_ID}`, () => HttpResponse.json({ data: role, meta: [] }))
}

const admin = withPermissions(['roles.view', 'roles.update', 'roles.delete'])

function renderPage(element: React.ReactElement) {
  renderWithProviders(
    <Routes>
      <Route path="/roles/:id" element={element} />
      <Route path="/roles/:id/edit" element={element} />
    </Routes>,
    { route: `/roles/${ROLE_ID}`, membership: admin },
  )
}

/**
 * Verrouillage des rôles livrés avec l'application.
 *
 * Le §11 les met hors d'atteinte d'un administrateur d'organisme. L'interface
 * ne se contente pas de masquer la suppression — elle retire aussi le lien de
 * modification, qui restait proposé pour un refus certain, et protège la page
 * d'édition atteinte directement par l'URL.
 */
describe('rôle système', () => {
  it('propose modification et suppression sur un rôle local', async () => {
    server.use(roleHandler(localRole))
    renderPage(<RoleDetailPage />)

    expect(await screen.findByRole('link', { name: 'Modifier' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Supprimer' })).toBeInTheDocument()
  })

  it('retire les deux actions sur un rôle système', async () => {
    server.use(roleHandler(systemRole))
    renderPage(<RoleDetailPage />)

    await screen.findByText('Lecture seule')
    expect(screen.queryByRole('link', { name: 'Modifier' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })

  it('refuse le formulaire d’édition d’un rôle système et l’explique', async () => {
    server.use(roleHandler(systemRole))
    renderPage(<RoleEditPage />)

    expect(
      await screen.findByText(
        'Rôle livré avec l’application : il n’est ni modifiable ni supprimable.',
      ),
    ).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Enregistrer' })).not.toBeInTheDocument()
  })
})
