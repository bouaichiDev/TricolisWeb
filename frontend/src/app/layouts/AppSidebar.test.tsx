import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'

import { AppSidebar } from './AppSidebar'
import { makeMembership, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'

/**
 * Menu dynamique.
 *
 * Le menu est une projection des permissions, pas une liste fixe. Ces tests
 * vérifient surtout ce que le §10 exige : un groupe dont aucune entrée n'est
 * autorisée disparaît **entièrement**, titre compris. Afficher « Administration »
 * vide inviterait à cliquer sur rien.
 */
describe('AppSidebar', () => {
  it('n’affiche que les entrées autorisées', () => {
    renderWithProviders(<AppSidebar />, {
      membership: withPermissions(['dashboard.view', 'customers.view']),
    })

    expect(screen.getByRole('link', { name: /tableau de bord/i })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /clients/i })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /agences/i })).not.toBeInTheDocument()
  })

  it('masque un groupe entier quand aucune de ses entrées n’est autorisée', () => {
    renderWithProviders(<AppSidebar />, {
      membership: withPermissions(['dashboard.view']),
    })

    expect(screen.queryByText('Administration')).not.toBeInTheDocument()
    expect(screen.queryByText('Ressources')).not.toBeInTheDocument()
  })

  it('affiche le groupe dès qu’une seule de ses entrées est autorisée', async () => {
    renderWithProviders(<AppSidebar />, {
      membership: withPermissions(['depots.view']),
    })

    // Le groupe est replié tant que la route courante ne lui appartient pas :
    // il faut l'ouvrir pour voir ses entrées.
    expect(screen.getByText('Ressources')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: /ressources/i }))

    expect(screen.getByRole('link', { name: /dépôts/i })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /agences/i })).not.toBeInTheDocument()
  })

  it('ouvre le groupe correspondant à la route courante', () => {
    renderWithProviders(<AppSidebar />, {
      route: '/roles',
      membership: withPermissions(['roles.view', 'users.view']),
    })

    expect(screen.getByRole('link', { name: /rôles/i })).toBeInTheDocument()
  })

  it('montre tout le menu au propriétaire, sans permission explicite', () => {
    renderWithProviders(<AppSidebar />, {
      membership: makeMembership({ isOwner: true, permissions: [] }),
    })

    expect(screen.getByText('Ressources')).toBeInTheDocument()
    expect(screen.getByText('Administration')).toBeInTheDocument()
  })
})
