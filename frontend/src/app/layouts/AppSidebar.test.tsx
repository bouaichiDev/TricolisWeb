import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { AppSidebar } from './AppSidebar'
import { menuHandler, menuItem, ORGANIZATION_MENU } from './menuTestData'
import { makeMembership, platformMembership } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

/**
 * La barre latérale rend ce que `GET /menu` lui donne.
 *
 * Le filtrage — portée du compte, entrées masquées par l'organisation,
 * permissions de l'utilisateur — est fait par le backend et vérifié là-bas.
 * Ici, on vérifie le rendu : hiérarchie, ouverture du groupe courant, états de
 * chargement.
 */
describe('AppSidebar', () => {
  it('rend les entrées renvoyées par l’API', async () => {
    server.use(menuHandler(ORGANIZATION_MENU))
    renderWithProviders(<AppSidebar />)

    expect(await screen.findByRole('link', { name: 'Clients' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Tableau de bord' })).toBeInTheDocument()
  })

  it('replie les enfants sous leur groupe', async () => {
    server.use(menuHandler(ORGANIZATION_MENU))
    renderWithProviders(<AppSidebar />)

    await screen.findByRole('link', { name: 'Clients' })

    // Repliés tant que la route courante n'appartient pas au groupe.
    expect(screen.queryByRole('link', { name: 'Agences' })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /ressources/i }))
    expect(screen.getByRole('link', { name: 'Agences' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Dépôts' })).toBeInTheDocument()
  })

  /**
   * Sans cette ouverture, l'utilisateur qui arrive par un lien direct verrait
   * son emplacement replié et se croirait ailleurs.
   */
  it('ouvre le groupe correspondant à la route courante', async () => {
    server.use(menuHandler(ORGANIZATION_MENU))
    renderWithProviders(<AppSidebar />, { route: '/depots' })

    expect(await screen.findByRole('link', { name: 'Dépôts' })).toBeInTheDocument()
  })

  it('n’affiche rien de ce que l’API n’a pas renvoyé', async () => {
    server.use(menuHandler([menuItem('customers', { labelKey: 'nav.customers' })]))
    renderWithProviders(<AppSidebar />)

    await screen.findByRole('link', { name: 'Clients' })
    expect(screen.queryByRole('link', { name: 'Agences' })).not.toBeInTheDocument()
    expect(screen.queryByText('Ressources')).not.toBeInTheDocument()
  })

  it('rend le menu plateforme quand c’est lui que l’API renvoie', async () => {
    server.use(
      menuHandler([
        menuItem('organizations', {
          labelKey: 'nav.organizations',
          icon: 'Building2',
          section: 'platform',
        }),
      ]),
    )
    renderWithProviders(<AppSidebar />, { membership: platformMembership() })

    expect(await screen.findByRole('link', { name: 'Organisations' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Clients' })).not.toBeInTheDocument()
  })

  describe('cible du logo', () => {
    it('mène un compte plateforme vers les organisations', async () => {
      server.use(menuHandler([]))
      renderWithProviders(<AppSidebar />, { membership: platformMembership() })

      expect(await screen.findByRole('link', { name: /Tricolis/ })).toHaveAttribute(
        'href',
        '/organizations',
      )
    })

    it('mène un compte d’organisme vers le tableau de bord', async () => {
      server.use(menuHandler([]))
      renderWithProviders(<AppSidebar />, { membership: makeMembership() })

      expect(await screen.findByRole('link', { name: /Tricolis/ })).toHaveAttribute(
        'href',
        '/dashboard',
      )
    })
  })

  /**
   * Une icône inconnue ne doit pas faire échouer le rendu : une entrée sans
   * icône reste utilisable, une barre latérale blanche ne l'est pas.
   */
  it('tolère un nom d’icône inconnu', async () => {
    server.use(
      menuHandler([menuItem('customers', { labelKey: 'nav.customers', icon: 'IconeInexistante' })]),
    )
    renderWithProviders(<AppSidebar />)

    expect(await screen.findByRole('link', { name: 'Clients' })).toBeInTheDocument()
  })

  it('n’affiche aucune entrée tant que le menu n’est pas chargé', () => {
    server.use(menuHandler(ORGANIZATION_MENU))
    renderWithProviders(<AppSidebar />)

    expect(screen.queryByRole('link', { name: 'Clients' })).not.toBeInTheDocument()
  })

  it('reste utilisable si le menu échoue à charger', async () => {
    server.use(
      http.get(`${API}/menu`, () =>
        HttpResponse.json({ message: 'Service indisponible.' }, { status: 500 }),
      ),
    )
    renderWithProviders(<AppSidebar />)

    // Le logo ramène à l'accueil : l'utilisateur n'est pas prisonnier de la page.
    expect(await screen.findByRole('link', { name: /Tricolis/ })).toBeInTheDocument()
  })
})
