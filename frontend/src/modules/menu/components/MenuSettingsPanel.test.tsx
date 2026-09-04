import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { CATALOGUE, menuHandler, renderPanel, ROLE_ID } from './menuSettingsTestData'
import { API, server } from '@/test/server'

/**
 * Réglage du menu par l'organisation : ce qu'elle voit.
 *
 * Ce fichier tient la visibilité et l'enregistrement. Ce qui touche à
 * l'apparence d'une entrée — son rang, son nom, son icône, son groupe — vit
 * dans `MenuSettingsCustomization.test.tsx` : les deux sujets ne se lisent pas
 * de la même façon, l'un dans l'écran, l'autre dans la charge utile.
 */
describe('MenuSettingsPanel', () => {
  it('liste le catalogue avec son état', async () => {
    server.use(menuHandler(CATALOGUE))
    renderPanel()

    expect(await screen.findByText('Clients')).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Clients' })).toBeChecked()
  })

  /**
   * L'administration ne se masque pas : un organisme qui la retirerait
   * n'aurait plus d'écran pour revenir en arrière. L'interrupteur est
   * désactivé, et la raison affichée plutôt que laissée à deviner.
   */
  it('verrouille les entrées que l’organisation doit garder', async () => {
    server.use(menuHandler(CATALOGUE))
    renderPanel()

    await screen.findByText('Clients')

    expect(screen.getByRole('switch', { name: 'Administration' })).toBeDisabled()
    expect(screen.getByText(/Toujours visible/)).toBeInTheDocument()
  })

  it('n’active l’enregistrement qu’après un changement', async () => {
    server.use(menuHandler(CATALOGUE))
    renderPanel()

    await screen.findByText('Clients')
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled()

    await userEvent.click(screen.getByRole('switch', { name: 'Clients' }))
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeEnabled()
  })

  it('envoie la visibilité de toutes les entrées', async () => {
    const bodies: Record<string, unknown>[] = []
    server.use(
      menuHandler(CATALOGUE),
      http.patch(`${API}/roles/${ROLE_ID}/menu`, async ({ request }) => {
        bodies.push((await request.json()) as Record<string, unknown>)

        return HttpResponse.json({ data: CATALOGUE, meta: [] })
      }),
    )
    renderPanel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('switch', { name: 'Clients' }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(bodies).toHaveLength(1)
    })

    const items = (bodies[0] as { items: { code: string; isVisible: boolean }[] }).items
    expect(items.find((item) => item.code === 'customers')?.isVisible).toBe(false)
    expect(items.find((item) => item.code === 'agencies')?.isVisible).toBe(true)
  })

  it('permet d’abandonner les changements', async () => {
    server.use(menuHandler(CATALOGUE))
    renderPanel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('switch', { name: 'Clients' }))
    await userEvent.click(screen.getByRole('button', { name: 'Annuler' }))

    expect(screen.getByRole('switch', { name: 'Clients' })).toBeChecked()
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled()
  })

  /**
   * Un rôle système ou plateforme se consulte, il ne se règle pas : les mêmes
   * trois conditions que `RolePolicy` côté serveur. Les répéter ici n'est pas
   * une duplication de la sécurité — c'est éviter de proposer un bouton qui
   * mènerait à un refus.
   */
  it('se consulte sans se régler sur un rôle verrouillé', async () => {
    server.use(menuHandler(CATALOGUE))
    renderPanel(false)

    await screen.findByText('Clients')

    expect(screen.getByRole('switch', { name: 'Clients' })).toBeDisabled()
    expect(screen.queryByRole('button', { name: 'Enregistrer' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Nouveau groupe' })).not.toBeInTheDocument()
  })

  it('remonte l’échec de chargement', async () => {
    server.use(
      http.get(`${API}/roles/${ROLE_ID}/menu`, () =>
        HttpResponse.json({ message: 'Service indisponible.' }, { status: 500 }),
      ),
    )
    renderPanel()

    expect(await screen.findByText('Service indisponible.')).toBeInTheDocument()
  })
})
