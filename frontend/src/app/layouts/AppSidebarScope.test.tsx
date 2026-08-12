import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'

import { AppSidebar } from './AppSidebar'
import { platformMembership, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'

/**
 * Le menu Administration selon la portée.
 *
 * Le §25 demande deux entrées mutuellement exclusives pour la même notion :
 * « Organisations » côté plateforme, « Mon organisation » côté organisme.
 * Afficher l'annuaire global à un administrateur local lui laissait croire à un
 * périmètre qu'il n'a pas.
 */
async function openAdministration() {
  await userEvent.click(screen.getByRole('button', { name: /administration/i }))
}

describe('AppSidebar, portée plateforme', () => {
  it('montre « Organisations » à un administrateur de plateforme', async () => {
    renderWithProviders(<AppSidebar />, {
      membership: platformMembership({
        permissions: [{ id: 'p1', code: 'organizations.view' }],
      }),
    })

    await openAdministration()

    expect(screen.getByRole('link', { name: 'Organisations' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Mon organisation' })).not.toBeInTheDocument()
  })

  it('montre « Mon organisation » à un administrateur d’organisme', async () => {
    renderWithProviders(<AppSidebar />, {
      membership: withPermissions(['organizations.view'], { isOwner: true }),
    })

    await openAdministration()

    expect(screen.getByRole('link', { name: 'Mon organisation' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Organisations' })).not.toBeInTheDocument()
  })

  it('mène « Mon organisation » vers la route dédiée, pas vers l’annuaire', async () => {
    renderWithProviders(<AppSidebar />, {
      membership: withPermissions(['organizations.view']),
    })

    await openAdministration()

    expect(screen.getByRole('link', { name: 'Mon organisation' })).toHaveAttribute(
      'href',
      '/my-organization',
    )
  })

  it('n’affiche ni l’une ni l’autre sans organizations.view', async () => {
    renderWithProviders(<AppSidebar />, {
      membership: withPermissions(['users.view']),
    })

    await openAdministration()

    expect(screen.queryByRole('link', { name: 'Organisations' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Mon organisation' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Utilisateurs' })).toBeInTheDocument()
  })
})
