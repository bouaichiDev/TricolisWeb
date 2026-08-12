import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'

import { AppSidebar } from './AppSidebar'
import { platformMembership, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'

/**
 * Deux menus, pas un menu filtré.
 *
 * Un compte plateforme administre les organisations inscrites ; les clients,
 * les agences et les dépôts appartiennent aux organismes. Lui présenter le menu
 * d'organisme, même expurgé, lui promettait un périmètre qui n'est pas le sien.
 */
const platform = platformMembership({
  permissions: [
    { id: 'p1', code: 'organizations.view' },
    { id: 'p2', code: 'organizations.create' },
    // Un compte plateforme est techniquement membre d'une organisation — le
    // schéma l'impose pour porter un rôle — et en détient donc les permissions.
    // Le menu ne doit pas s'en servir.
    { id: 'p3', code: 'customers.view' },
    { id: 'p4', code: 'users.view' },
    { id: 'p5', code: 'roles.view' },
    { id: 'p6', code: 'audit.view' },
    { id: 'p7', code: 'dashboard.view' },
  ],
})

const localOwner = withPermissions(
  ['dashboard.view', 'customers.view', 'organizations.view', 'users.view', 'roles.view'],
  { isOwner: true },
)

describe('AppSidebar, compte plateforme', () => {
  it('affiche « Organisations » comme entrée de premier niveau', () => {
    renderWithProviders(<AppSidebar />, { membership: platform })

    expect(screen.getByRole('link', { name: 'Organisations' })).toBeInTheDocument()
  })

  /**
   * Le défaut corrigé : le compte plateforme voyait Clients, Ressources et tout
   * le menu Administration, faute de détenir les permissions correspondantes
   * par son appartenance technique.
   */
  it('n’affiche ni Clients, ni Ressources, ni Administration', () => {
    renderWithProviders(<AppSidebar />, { membership: platform })

    expect(screen.queryByRole('link', { name: 'Clients' })).not.toBeInTheDocument()
    expect(screen.queryByText('Ressources')).not.toBeInTheDocument()
    expect(screen.queryByText('Administration')).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Mon organisation' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /tableau de bord/i })).not.toBeInTheDocument()
  })

  it('mène le logo vers les organisations, pas vers le tableau de bord', () => {
    renderWithProviders(<AppSidebar />, { membership: platform })

    expect(screen.getByRole('link', { name: /Tricolis/ })).toHaveAttribute(
      'href',
      '/organizations',
    )
  })
})

describe('AppSidebar, compte d’organisme', () => {
  it('affiche son activité et son administration', async () => {
    renderWithProviders(<AppSidebar />, { membership: localOwner })

    expect(screen.getByRole('link', { name: 'Clients' })).toBeInTheDocument()
    expect(screen.getByText('Administration')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /administration/i }))
    expect(screen.getByRole('link', { name: 'Mon organisation' })).toBeInTheDocument()
  })

  it('n’affiche pas l’annuaire global des organisations', async () => {
    renderWithProviders(<AppSidebar />, { membership: localOwner })

    await userEvent.click(screen.getByRole('button', { name: /administration/i }))
    expect(screen.queryByRole('link', { name: 'Organisations' })).not.toBeInTheDocument()
  })

  it('mène le logo vers le tableau de bord', () => {
    renderWithProviders(<AppSidebar />, { membership: localOwner })

    expect(screen.getByRole('link', { name: /Tricolis/ })).toHaveAttribute('href', '/dashboard')
  })
})
