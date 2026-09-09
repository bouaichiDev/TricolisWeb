import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Eye, Pencil, Trash2 } from 'lucide-react'
import { describe, expect, it, vi } from 'vitest'

import { RowActions } from './RowActions'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'

/**
 * La colonne qui remplace le clic sur la ligne.
 *
 * Ce qu'elle doit tenir tient en quatre points, et aucun ne se voit tant qu'il
 * tient : une action qui ouvre un écran est un **lien** — donc ouvrable dans un
 * onglet — ; une action interdite n'est pas proposée ; l'action destructrice se
 * distingue ; et un clic dessus ne déclenche pas ce que la ligne déclencherait.
 */
describe('RowActions', () => {
  it('rend un lien pour une action qui ouvre un écran', () => {
    renderWithProviders(
      <RowActions actions={[{ key: 'view', icon: Eye, label: 'Consulter', to: '/orders/1' }]} />,
    )

    expect(screen.getByRole('link', { name: 'Consulter' })).toHaveAttribute('href', '/orders/1')
  })

  it('rend un bouton pour une action qui n’ouvre rien', async () => {
    const onClick = vi.fn()
    renderWithProviders(
      <RowActions actions={[{ key: 'edit', icon: Pencil, label: 'Modifier', onClick }]} />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Modifier' }))

    expect(onClick).toHaveBeenCalledOnce()
  })

  /**
   * Masquer, pas désactiver : un bouton grisé invite à chercher pourquoi, alors
   * que l'action n'existe simplement pas pour ce rôle. La protection réelle
   * reste côté serveur.
   */
  it('ne propose pas une action dont le droit manque', () => {
    renderWithProviders(
      <RowActions
        actions={[
          { key: 'view', icon: Eye, label: 'Consulter', to: '/orders/1' },
          {
            key: 'delete',
            icon: Trash2,
            label: 'Supprimer',
            permission: 'orders.delete',
            onClick: vi.fn(),
          },
        ]}
      />,
      { membership: withPermissions(['orders.view']) },
    )

    expect(screen.getByRole('link', { name: 'Consulter' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })

  /**
   * Le clic ne remonte pas : là où une ligne reste cliquable — un panneau, une
   * sélection — « Supprimer » ne doit pas déclencher les deux.
   */
  it('ne laisse pas le clic remonter à la ligne', async () => {
    const onRowClick = vi.fn()
    const onClick = vi.fn()

    renderWithProviders(
      <tr onClick={onRowClick}>
        <td>
          <RowActions actions={[{ key: 'edit', icon: Pencil, label: 'Modifier', onClick }]} />
        </td>
      </tr>,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Modifier' }))

    expect(onClick).toHaveBeenCalledOnce()
    expect(onRowClick).not.toHaveBeenCalled()
  })
})
