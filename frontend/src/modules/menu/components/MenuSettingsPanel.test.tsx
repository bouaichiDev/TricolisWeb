import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { MenuSettingsPanel } from './MenuSettingsPanel'
import { catalogueHandler, menuItem } from '@/app/layouts/menuTestData'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const CATALOGUE = [
  menuItem('customers', { labelKey: 'nav.customers', position: 10 }),
  menuItem('agencies', { labelKey: 'nav.agencies', position: 21, parent: 'resources' }),
  menuItem('administration', {
    labelKey: 'nav.administration',
    route: null,
    position: 80,
    canHide: false,
  }),
]

function panel() {
  renderWithProviders(<MenuSettingsPanel />, {
    membership: withPermissions(['organizations.update']),
  })
}

/**
 * Réglage du menu par l'organisation.
 *
 * L'organisation choisit quelles entrées elle voit — pas leur libellé ni leur
 * destination, qui appartiennent au catalogue en code.
 */
describe('MenuSettingsPanel', () => {
  it('liste le catalogue avec son état', async () => {
    server.use(catalogueHandler(CATALOGUE))
    panel()

    expect(await screen.findByText('Clients')).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Clients' })).toBeChecked()
  })

  /**
   * L'administration ne se masque pas : un organisme qui la retirerait
   * n'aurait plus d'écran pour revenir en arrière. L'interrupteur est
   * désactivé, et la raison affichée plutôt que laissée à deviner.
   */
  it('verrouille les entrées que l’organisation doit garder', async () => {
    server.use(catalogueHandler(CATALOGUE))
    panel()

    await screen.findByText('Clients')

    expect(screen.getByRole('switch', { name: 'Administration' })).toBeDisabled()
    expect(screen.getByText(/Toujours visible/)).toBeInTheDocument()
  })

  it('n’active l’enregistrement qu’après un changement', async () => {
    server.use(catalogueHandler(CATALOGUE))
    panel()

    await screen.findByText('Clients')
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled()

    await userEvent.click(screen.getByRole('switch', { name: 'Clients' }))
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeEnabled()
  })

  it('envoie la visibilité de toutes les entrées', async () => {
    const bodies: Record<string, unknown>[] = []
    server.use(
      catalogueHandler(CATALOGUE),
      http.patch(`${API}/menu`, async ({ request }) => {
        bodies.push((await request.json()) as Record<string, unknown>)

        return HttpResponse.json({ data: CATALOGUE, meta: [] })
      }),
    )
    panel()

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
    server.use(catalogueHandler(CATALOGUE))
    panel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('switch', { name: 'Clients' }))
    await userEvent.click(screen.getByRole('button', { name: 'Annuler' }))

    expect(screen.getByRole('switch', { name: 'Clients' })).toBeChecked()
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled()
  })

  it('remonte l’échec de chargement', async () => {
    server.use(
      http.get(`${API}/menu/catalogue`, () =>
        HttpResponse.json({ message: 'Service indisponible.' }, { status: 500 }),
      ),
    )
    panel()

    expect(await screen.findByText('Service indisponible.')).toBeInTheDocument()
  })
})
