import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { OrganizationLogoPanel } from './OrganizationLogoPanel'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const ORG_ID = 'org-1'

/** Un PNG minuscule mais valide, servi comme le ferait l'API. */
const PIXEL = Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUg=='), (c) => c.charCodeAt(0))

function logoHandler() {
  return http.get(`${API}/organizations/${ORG_ID}/logo`, () =>
    HttpResponse.arrayBuffer(PIXEL.buffer as ArrayBuffer, {
      headers: { 'Content-Type': 'image/png' },
    }),
  )
}

function panel(hasLogo: boolean) {
  renderWithProviders(<OrganizationLogoPanel organizationId={ORG_ID} hasLogo={hasLogo} />, {
    membership: withPermissions(['organizations.update']),
  })
}

/**
 * Logo de l'organisation.
 *
 * Il n'est pas décoratif : les modèles de facture l'appellent, et le PDF
 * l'embarque. L'écran le dépose, le remplace et le retire.
 */
describe('OrganizationLogoPanel', () => {
  it('propose de choisir une image quand il n’y en a pas', () => {
    panel(false)

    expect(screen.getByRole('button', { name: 'Choisir une image' })).toBeInTheDocument()
    // Rien à retirer tant que rien n'est déposé.
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })

  /**
   * Ce que l'écran doit garantir : un champ `logo`, et des octets dedans. Ni le
   * **nom** ni la **taille** ne sont vérifiés — jsdom rebaptise « blob » ce qui
   * traverse son `FormData` et n'en conserve pas la longueur. Les affirmer
   * testerait l'environnement plutôt que l'écran.
   */
  it('envoie le fichier choisi', async () => {
    const sent: { named: boolean; empty: boolean }[] = []
    server.use(
      logoHandler(),
      http.post(`${API}/organizations/${ORG_ID}/logo`, async ({ request }) => {
        const body = await request.formData()
        const logo = body.get('logo') as File

        sent.push({ named: logo !== null, empty: logo.size === 0 })

        return HttpResponse.json({ data: { hasLogo: true }, meta: [] })
      }),
    )
    panel(false)

    const file = new File([PIXEL], 'logo.png', { type: 'image/png' })
    await userEvent.upload(screen.getByLabelText('Choisir une image'), file)

    await waitFor(() => {
      expect(sent).toEqual([{ named: true, empty: false }])
    })
  })

  /**
   * L'image se demande à part : la route est authentifiée, et un `src` posé
   * directement partirait sans en-tête pour revenir en 401.
   */
  it('affiche le logo déposé', async () => {
    server.use(logoHandler())
    panel(true)

    const image = await screen.findByRole('img', { name: 'Logo' })

    expect(image).toHaveAttribute('src', expect.stringContaining('blob:'))
  })

  it('retire le logo', async () => {
    let removed = false
    server.use(
      logoHandler(),
      http.delete(`${API}/organizations/${ORG_ID}/logo`, () => {
        removed = true

        return HttpResponse.json({ data: { hasLogo: false }, meta: [] })
      }),
    )
    panel(true)

    await userEvent.click(screen.getByRole('button', { name: 'Supprimer' }))

    await waitFor(() => {
      expect(removed).toBe(true)
    })
  })

  /** Le bouton dit « Remplacer » dès qu'il y a quelque chose à remplacer. */
  it('parle de remplacement quand un logo existe', async () => {
    server.use(logoHandler())
    panel(true)

    expect(await screen.findByRole('button', { name: 'Remplacer' })).toBeInTheDocument()
  })
})
