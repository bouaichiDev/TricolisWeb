import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { PermissionGuard } from './PermissionGuard'
import { makeMembership, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'

describe('PermissionGuard', () => {
  it('affiche le contenu quand la permission est accordée', () => {
    renderWithProviders(
      <PermissionGuard permission="customers.create">
        <button>Nouveau client</button>
      </PermissionGuard>,
      { membership: withPermissions(['customers.create']) },
    )

    expect(screen.getByRole('button', { name: 'Nouveau client' })).toBeInTheDocument()
  })

  it('masque le contenu quand la permission manque', () => {
    renderWithProviders(
      <PermissionGuard permission="customers.create">
        <button>Nouveau client</button>
      </PermissionGuard>,
      { membership: withPermissions(['customers.view']) },
    )

    expect(screen.queryByRole('button', { name: 'Nouveau client' })).not.toBeInTheDocument()
  })

  it('affiche le repli plutôt que rien lorsqu’il est fourni', () => {
    renderWithProviders(
      <PermissionGuard permission="customers.delete" fallback={<span>Action indisponible</span>}>
        <button>Supprimer</button>
      </PermissionGuard>,
      { membership: withPermissions([]) },
    )

    expect(screen.getByText('Action indisponible')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })

  it('accepte une liste : une seule permission suffit par défaut', () => {
    renderWithProviders(
      <PermissionGuard permission={['customers.update', 'customers.block']}>
        <span>Actions</span>
      </PermissionGuard>,
      { membership: withPermissions(['customers.block']) },
    )

    expect(screen.getByText('Actions')).toBeInTheDocument()
  })

  it('exige toutes les permissions quand requireAll est demandé', () => {
    renderWithProviders(
      <PermissionGuard permission={['customers.update', 'customers.block']} requireAll>
        <span>Actions</span>
      </PermissionGuard>,
      { membership: withPermissions(['customers.block']) },
    )

    expect(screen.queryByText('Actions')).not.toBeInTheDocument()
  })

  /**
   * Le propriétaire contourne le contrôle côté backend
   * (`BaseOrganizationPolicy`) ; l'interface doit dire la même chose, sinon
   * elle masque un bouton que l'API accepterait.
   */
  it('laisse passer le propriétaire sans permission explicite', () => {
    renderWithProviders(
      <PermissionGuard permission="customers.delete">
        <button>Supprimer</button>
      </PermissionGuard>,
      { membership: makeMembership({ isOwner: true, permissions: [] }) },
    )

    expect(screen.getByRole('button', { name: 'Supprimer' })).toBeInTheDocument()
  })
})
