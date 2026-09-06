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
 * Le logo occupe seul l'en-tête : plus aucun nom ne l'accompagne à l'écran.
 * C'est son `alt` qui le nomme — donc le nom accessible du lien —, et c'est
 * cela que ces tests interrogent. L'image est cherchée dans le DOM plutôt que
 * par son rôle : les cas sans logo doivent pouvoir affirmer qu'il n'y en a
 * aucune.
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

  /**
   * Le nom ne disparaît pas, il change de place : il devient le texte
   * alternatif de l'image, seul moyen de nommer un lien qui n'affiche plus
   * aucun mot.
   */
  it('n’affiche que le logo de l’organisation, nommé par son alt', async () => {
    const membership = makeMembership({ hasLogo: true, name: 'Atlas Transport' })
    server.use(logoHandler(membership.id))

    const { container } = render(membership)

    await waitFor(() => expect(logoOf(container)).not.toBeNull())
    expect(logoOf(container)?.alt).toBe('Atlas Transport')
    expect(screen.queryByText('Atlas Transport')).not.toBeInTheDocument()
    expect(screen.queryByText('Tricolis')).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Atlas Transport' })).toBeInTheDocument()
  })

  /**
   * Le deuxième niveau de repli : l'identité que pose un intégrateur sur l'outil
   * qu'il revend. C'est le nom de l'application qui la nomme — celui de
   * l'organisation ferait passer l'image pour la sienne.
   */
  it('se replie sur le logo de l’installation quand l’organisation n’en a pas', async () => {
    server.use(http.get(`${API}/configuration/logo`, PNG))

    const { container } = render(makeMembership({ hasLogo: false, name: 'Atlas Transport' }), true)

    await waitFor(() => expect(logoOf(container)).not.toBeNull())
    expect(logoOf(container)?.alt).toBe('Tricolis')
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
