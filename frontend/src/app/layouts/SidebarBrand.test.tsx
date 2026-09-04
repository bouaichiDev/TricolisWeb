import { screen, waitFor } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { SidebarBrand } from './SidebarBrand'
import { makeMembership, platformMembership } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'
import type { AuthMembership } from '@/shared/types/auth'

/**
 * L'identité que porte la barre latérale.
 *
 * Trois cas, et le troisième est celui qu'on oublie : l'organisation a un logo,
 * elle n'en a pas, ou le compte n'appartient à aucune organisation. Ce dernier
 * est celui d'un administrateur plateforme — il administre l'outil, pas un
 * organisme, et lui poser le logo d'une organisation quelconque serait faux.
 */
const PNG = () =>
  HttpResponse.arrayBuffer(new Uint8Array([137, 80, 78, 71]).buffer, {
    headers: { 'Content-Type': 'image/png' },
  })

function logoHandler(organizationId: string) {
  return http.get(`${API}/organizations/${organizationId}/logo`, PNG)
}

/**
 * La configuration de l'installation est demandée à chaque rendu de l'en-tête :
 * c'est elle qui dit s'il existe un logo par défaut vers lequel se replier.
 */
function configurationHandler(hasDefaultLogo: boolean) {
  return http.get(`${API}/configuration`, () =>
    HttpResponse.json({ data: { hasDefaultLogo }, meta: [] }),
  )
}

function render(membership: AuthMembership, hasDefaultLogo = false) {
  server.use(configurationHandler(hasDefaultLogo))

  return renderWithProviders(<SidebarBrand />, { membership })
}

/**
 * Le logo porte `alt=""` — il est décoratif, le nom de l'organisation étant
 * juste à côté. Il sort donc de l'arbre d'accessibilité, et `getByRole('img')`
 * ne le trouve pas : c'est le comportement voulu, et on l'interroge dans le DOM.
 */
function logoOf(container: HTMLElement): HTMLImageElement | null {
  return container.querySelector('img')
}

describe('identité de la barre latérale', () => {
  it('porte celle de l’application quand l’organisation n’a pas de logo', () => {
    const { container } = render(makeMembership({ hasLogo: false }))

    expect(screen.getByText('Tricolis')).toBeInTheDocument()
    // Aucun gestionnaire de logo n'est déclaré : le serveur de test échouerait
    // sur un appel imprévu. Le test tient donc par ce qu'il ne provoque pas —
    // `hasLogo` faux doit suffire à ne rien demander.
    expect(logoOf(container)).toBeNull()
  })

  it('porte le logo et le nom de l’organisation quand elle en a un', async () => {
    const membership = makeMembership({ hasLogo: true, name: 'Atlas Transport' })
    server.use(logoHandler(membership.id))

    const { container } = render(membership)

    await waitFor(() => expect(logoOf(container)).not.toBeNull())
    expect(screen.getByText('Atlas Transport')).toBeInTheDocument()
    expect(screen.queryByText('Tricolis')).not.toBeInTheDocument()
  })

  /**
   * Le deuxième niveau de repli : l'identité que pose un intégrateur sur l'outil
   * qu'il revend. Le nom reste celui de l'application — celui de l'organisation
   * ferait passer l'image pour la sienne.
   */
  it('se replie sur le logo de l’installation quand l’organisation n’en a pas', async () => {
    server.use(http.get(`${API}/configuration/logo`, PNG))

    const { container } = render(makeMembership({ hasLogo: false, name: 'Atlas Transport' }), true)

    await waitFor(() => expect(logoOf(container)).not.toBeNull())
    expect(screen.getByText('Tricolis')).toBeInTheDocument()
    expect(screen.queryByText('Atlas Transport')).not.toBeInTheDocument()
  })

  /**
   * Un compte plateforme n'agit dans aucune organisation : lui poser un logo
   * d'organisme laisserait croire qu'il en administre une en particulier.
   */
  it('garde l’identité de l’application pour un compte plateforme', () => {
    const { container } = render(platformMembership({ hasLogo: true }))

    expect(screen.getByText('Tricolis')).toBeInTheDocument()
    expect(logoOf(container)).toBeNull()
  })
})
