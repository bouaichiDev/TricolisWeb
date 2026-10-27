import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Eye, Pencil, PowerOff } from 'lucide-react'

import { renderWithAuth } from '@/test/renderWithAuth'

import { RowActions } from './RowActions'

/**
 * La colonne d'actions du back-office, dans le portail : une action qui ouvre
 * un écran est un lien, une action interdite n'est pas proposée, l'action
 * destructrice se distingue, et chaque icône porte un libellé lisible.
 */
describe('RowActions', () => {
  it('renders a link for an action that opens a screen', async () => {
    renderWithAuth(<RowActions actions={[{ key: 'view', icon: Eye, label: 'Voir', to: '/orders/1' }]} />)

    expect(await screen.findByRole('link', { name: 'Voir' })).toHaveAttribute('href', '/orders/1')
  })

  it('renders a button for an action that opens nothing', async () => {
    const onClick = vi.fn()
    renderWithAuth(<RowActions actions={[{ key: 'off', icon: PowerOff, label: 'Désactiver', onClick, tone: 'danger' }]} />)

    const button = await screen.findByRole('button', { name: 'Désactiver' })
    await userEvent.setup().click(button)

    expect(onClick).toHaveBeenCalledOnce()
    expect(button).toHaveAttribute('title', 'Désactiver')
    expect(button.className).toContain('text-destructive')
  })

  it('does not offer an action whose permission is missing', async () => {
    renderWithAuth(
      <RowActions
        actions={[
          { key: 'view', icon: Eye, label: 'Voir', to: '/catalog/1' },
          { key: 'edit', icon: Pencil, label: 'Modifier', onClick: () => undefined, permission: 'provider.drivers.update' },
        ]}
      />,
      ['provider.dashboard.view'],
    )

    expect(await screen.findByRole('link', { name: 'Voir' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Modifier' })).not.toBeInTheDocument()
  })
})
