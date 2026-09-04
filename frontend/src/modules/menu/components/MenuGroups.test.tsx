import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { CATALOGUE, menuHandler, renderPanel, ROLE_ID } from './menuSettingsTestData'
import { menuItem } from '@/app/layouts/menuTestData'
import { API, server } from '@/test/server'

/** Un groupe créé, tel que le serveur le renvoie : sans route, supprimable. */
const CUSTOM = menuItem('grp-abc', {
  label: 'Mon pôle',
  icon: 'Folder',
  route: null,
  position: 30,
  isCustom: true,
})

/**
 * Groupes de menu créés par l'organisation.
 *
 * Un groupe n'ouvre rien : ni route, ni permission. C'est ce qui permet d'en
 * créer là où le reste du menu reste figé en code — il ne peut mener nulle
 * part, donc pas non plus à « Page introuvable ».
 */
describe('groupes de menu créés', () => {
  it('crée un groupe depuis un nom et une icône', async () => {
    const created: { label: string; icon: string }[] = []
    server.use(
      menuHandler(CATALOGUE),
      http.post(`${API}/roles/${ROLE_ID}/menu/groups`, async ({ request }) => {
        created.push((await request.json()) as { label: string; icon: string })

        return HttpResponse.json({ data: [...CATALOGUE, CUSTOM], meta: [] })
      }),
    )
    renderPanel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('button', { name: 'Nouveau groupe' }))
    await userEvent.type(screen.getByLabelText('Nom affiché'), 'Mon pôle')
    await userEvent.click(screen.getByRole('radio', { name: 'MapPin' }))
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(created).toEqual([{ label: 'Mon pôle', icon: 'MapPin' }])
    })

    // La réponse porte le catalogue entier : le groupe paraît sans rechargement.
    expect(await screen.findByText('Mon pôle')).toBeInTheDocument()
  })

  /**
   * Un groupe sans nom afficherait un titre vide dans la barre latérale,
   * impossible à retrouver pour le corriger.
   */
  it('n’autorise pas un groupe sans nom', async () => {
    server.use(menuHandler(CATALOGUE))
    renderPanel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('button', { name: 'Nouveau groupe' }))

    expect(screen.getByRole('button', { name: 'Créer' })).toBeDisabled()

    await userEvent.type(screen.getByLabelText('Nom affiché'), '   ')
    expect(screen.getByRole('button', { name: 'Créer' })).toBeDisabled()
  })

  /**
   * Un groupe vide ne s'affiche pas dans la barre latérale : le dire ici évite
   * de croire la création ratée.
   */
  it('signale qu’un groupe vide reste masqué', async () => {
    server.use(menuHandler([...CATALOGUE, CUSTOM]))
    renderPanel()

    expect(await screen.findByText(/Vide —/)).toBeInTheDocument()
  })

  it('propose un groupe créé comme emplacement d’accueil', async () => {
    server.use(menuHandler([...CATALOGUE, CUSTOM]))
    renderPanel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('button', { name: 'Personnaliser « Clients »' }))
    await userEvent.click(screen.getByRole('combobox'))

    expect(await screen.findByRole('option', { name: 'Mon pôle' })).toBeInTheDocument()
  })

  /**
   * Seul un groupe créé se supprime : un groupe livré n'appartient pas à
   * l'organisation, elle le masque.
   */
  it('ne propose la suppression que sur un groupe créé', async () => {
    server.use(menuHandler([...CATALOGUE, CUSTOM]))
    renderPanel()

    await screen.findByText('Clients')

    expect(screen.getByRole('button', { name: 'Supprimer « Mon pôle »' })).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Supprimer « Ressources »' }),
    ).not.toBeInTheDocument()
  })

  it('supprime un groupe après confirmation', async () => {
    const deleted: string[] = []
    server.use(
      menuHandler([...CATALOGUE, CUSTOM]),
      http.delete(`${API}/roles/${ROLE_ID}/menu/groups/:code`, ({ params }) => {
        deleted.push(params.code as string)

        return HttpResponse.json({ data: CATALOGUE, meta: [] })
      }),
    )
    renderPanel()

    await screen.findByText('Mon pôle')
    await userEvent.click(screen.getByRole('button', { name: 'Supprimer « Mon pôle »' }))

    expect(await screen.findByText(/ne sont pas supprimées/)).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Supprimer' }))

    await waitFor(() => {
      expect(deleted).toEqual(['grp-abc'])
    })
  })
})
