import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

import { OrganizationSwitcher } from './OrganizationSwitcher'
import { makeMembership } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'

const first = makeMembership({ id: 'ORG-1', name: 'Tricolis SAS', isPrimary: true })
const second = makeMembership({
  id: 'ORG-2',
  code: 'NORD',
  name: 'Tricolis Nord',
  isPrimary: false,
  isOwner: true,
})

describe('OrganizationSwitcher', () => {
  /** Un menu à un seul choix n'est pas un choix : il devient un libellé. */
  it('affiche un simple libellé avec une seule appartenance', () => {
    renderWithProviders(<OrganizationSwitcher />, { membership: first })

    expect(screen.getByText('Tricolis SAS')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /organisation/i })).not.toBeInTheDocument()
  })

  it('propose un menu dès qu’il y a plusieurs appartenances', async () => {
    renderWithProviders(<OrganizationSwitcher />, {
      membership: first,
      memberships: [first, second],
    })

    await userEvent.click(screen.getByRole('button', { name: /organisation/i }))

    expect(await screen.findByRole('menuitem', { name: /Tricolis Nord/ })).toBeInTheDocument()
    expect(screen.getByRole('menuitem', { name: /Tricolis SAS/ })).toBeInTheDocument()
  })

  /**
   * Changer d'organisation change l'en-tête `X-Organization-Id`, donc tout ce
   * que l'API renverra ensuite — et les permissions avec, puisqu'elles sont
   * portées par l'appartenance.
   */
  it('remonte l’organisation choisie', async () => {
    const onSwitchOrganization = vi.fn()
    renderWithProviders(<OrganizationSwitcher />, {
      membership: first,
      memberships: [first, second],
      onSwitchOrganization,
    })

    await userEvent.click(screen.getByRole('button', { name: /organisation/i }))
    await userEvent.click(await screen.findByRole('menuitem', { name: /Tricolis Nord/ }))

    expect(onSwitchOrganization).toHaveBeenCalledWith('ORG-2')
  })

  it('signale l’appartenance dont l’utilisateur est propriétaire', async () => {
    renderWithProviders(<OrganizationSwitcher />, {
      membership: first,
      memberships: [first, second],
    })

    await userEvent.click(screen.getByRole('button', { name: /organisation/i }))

    const owned = await screen.findByRole('menuitem', { name: /Tricolis Nord/ })
    expect(owned).toHaveTextContent('Propriétaire')
  })

  it('n’affiche rien tant qu’aucune appartenance n’est résolue', () => {
    renderWithProviders(<OrganizationSwitcher />, {
      membership: null,
      memberships: [],
    })

    // Le sélecteur ne rend aucun élément propre. Le conteneur, lui, n'est pas
    // vide : les fournisseurs y montent la zone de notifications, comme en
    // production.
    expect(screen.queryByRole('button', { name: /organisation/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('menuitem')).not.toBeInTheDocument()
  })
})
