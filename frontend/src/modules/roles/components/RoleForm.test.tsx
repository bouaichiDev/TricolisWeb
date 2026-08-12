import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'

import { RoleForm } from './RoleForm'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

/**
 * `GET /permissions` ne renvoie que l'ensemble délégable.
 *
 * Le filtrage est fait par le backend : le frontend n'a rien à retrancher, et
 * ne le doit pas — il n'a aucun moyen fiable de savoir ce qui est réservé. Ces
 * tests vérifient que le formulaire se contente de ce qu'il reçoit.
 */
const delegable = [
  { id: 'p1', code: 'customers.view', name: 'Voir les clients', module: 'customers', action: 'view' },
  {
    id: 'p2',
    code: 'customers.create',
    name: 'Créer un client',
    module: 'customers',
    action: 'create',
  },
  { id: 'p3', code: 'agencies.view', name: 'Voir les agences', module: 'agencies', action: 'view' },
]

function permissionsHandler(rows = delegable) {
  return http.get(`${API}/permissions`, () => HttpResponse.json({ data: rows, meta: [] }))
}

const assigner = withPermissions(['roles.create', 'roles.assign_permissions'])

function setup(onSubmit = vi.fn().mockResolvedValue(undefined)) {
  server.use(permissionsHandler())
  renderWithProviders(
    <RoleForm
      onSubmit={onSubmit}
      onCancel={vi.fn()}
      submitLabel="Créer"
      organizationName="Atlas Transport"
    />,
    { membership: assigner },
  )

  return onSubmit
}

describe('RoleForm', () => {
  /**
   * Le champ existait et était librement saisissable : y écrire « platform »
   * suffisait, le backend enregistrait la valeur telle quelle.
   */
  it('n’expose aucun champ de portée saisissable', async () => {
    setup()
    await screen.findByText('Voir les clients')

    expect(screen.queryByLabelText(/portée/i)).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox', { name: /portée/i })).not.toBeInTheDocument()
  })

  it('n’expose aucun champ « rôle système »', async () => {
    setup()
    await screen.findByText('Voir les clients')

    expect(screen.queryByLabelText(/système/i)).not.toBeInTheDocument()
  })

  it('affiche la portée et l’organisation en lecture', async () => {
    setup()
    await screen.findByText('Voir les clients')

    // « Organisation » apparaît deux fois : comme libellé du champ et comme
    // valeur de la portée. La recherche porte donc sur la paire libellé/valeur.
    const organization = screen.getByText('Organisation', { selector: 'dt' }).parentElement
    expect(organization).toHaveTextContent('Atlas Transport')

    const scope = screen.getByText('Portée', { selector: 'dt' }).parentElement
    expect(scope).toHaveTextContent('Organisation')
  })

  it('n’envoie ni portée ni drapeau système', async () => {
    const onSubmit = setup()
    await screen.findByText('Voir les clients')

    await userEvent.type(screen.getByLabelText(/^code/i), 'operateur')
    await userEvent.type(screen.getByLabelText(/^nom/i), 'Opérateur')
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledOnce()
    })
    expect(onSubmit.mock.calls[0][0]).toEqual({
      code: 'operateur',
      name: 'Opérateur',
      status: 'active',
    })
  })

  describe('permissions', () => {
    it('ne propose que les permissions renvoyées par l’API', async () => {
      setup()

      expect(await screen.findByText('Voir les clients')).toBeInTheDocument()
      expect(screen.queryByText('Créer une organisation')).not.toBeInTheDocument()
      expect(screen.queryByText('organizations.create')).not.toBeInTheDocument()
    })

    /**
     * « Tout cocher » agit sur les cases affichées, donc sur les seules
     * permissions délégables. Il ne peut pas sélectionner ce qui n'est pas là.
     */
    it('« Tout cocher » ne sélectionne que les permissions du groupe affiché', async () => {
      const onSubmit = setup()
      await screen.findByText('Voir les clients')

      const buttons = screen.getAllByRole('button', { name: 'Tout cocher' })
      await userEvent.click(buttons[0])

      await userEvent.type(screen.getByLabelText(/^code/i), 'operateur')
      await userEvent.type(screen.getByLabelText(/^nom/i), 'Opérateur')
      await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

      await waitFor(() => {
        expect(onSubmit).toHaveBeenCalledOnce()
      })

      const selected = onSubmit.mock.calls[0][1] as string[]
      expect(selected).not.toContain('p-platform')
      expect(selected.every((id) => delegable.some((permission) => permission.id === id))).toBe(true)
    })

    it('affiche le décompte sur le seul ensemble délégable', async () => {
      setup()

      expect(await screen.findByText('0 permission sélectionnée sur 3 attribuable')).toBeInTheDocument()
    })
  })
})
