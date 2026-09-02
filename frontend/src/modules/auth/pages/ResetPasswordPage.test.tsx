import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { ResetPasswordPage } from './ResetPasswordPage'

function render(query = '?token=tok-123&email=alex%40mail.com') {
  const bodies: Record<string, unknown>[] = []

  server.use(
    http.post(`${API}/auth/reset-password`, async ({ request }) => {
      bodies.push((await request.json()) as Record<string, unknown>)

      return HttpResponse.json({ message: 'ok' })
    }),
  )

  renderWithProviders(<ResetPasswordPage />, { route: `/reset-password${query}` })

  return bodies
}

/**
 * On arrive ici parce qu'on ne peut plus se connecter : c'est le jeton de l'URL
 * qui authentifie, et rien d'autre.
 */
describe('choisir un nouveau mot de passe', () => {
  it('reprend le jeton et l’adresse du lien', async () => {
    const bodies = render()

    await userEvent.type(screen.getByLabelText(/^Nouveau mot de passe/), 'Tricolis!2026')
    await userEvent.type(screen.getByLabelText(/^Confirmation/), 'Tricolis!2026')
    await userEvent.click(screen.getByRole('button', { name: /Enregistrer/ }))

    await waitFor(() => expect(bodies).toHaveLength(1))
    expect(bodies[0]).toMatchObject({
      token: 'tok-123',
      email: 'alex@mail.com',
      password: 'Tricolis!2026',
      password_confirmation: 'Tricolis!2026',
    })
  })

  /**
   * Un lien tronqué par un client de messagerie doit se voir tout de suite, et
   * non après une saisie perdue.
   */
  it('n’offre pas de formulaire sans jeton', () => {
    render('')

    expect(screen.getByText(/Ce lien est incomplet/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Nouveau mot de passe/)).not.toBeInTheDocument()
  })

  it('refuse deux saisies différentes', async () => {
    const bodies = render()

    await userEvent.type(screen.getByLabelText(/^Nouveau mot de passe/), 'Tricolis!2026')
    await userEvent.type(screen.getByLabelText(/^Confirmation/), 'Tricolis!2027')
    await userEvent.click(screen.getByRole('button', { name: /Enregistrer/ }))

    expect(await screen.findByText('Les deux saisies diffèrent.')).toBeInTheDocument()
    expect(bodies).toHaveLength(0)
  })

  /** Le refus du serveur — jeton périmé — se lit tel quel. */
  it('montre le refus du serveur', async () => {
    render()

    server.use(
      http.post(`${API}/auth/reset-password`, () =>
        HttpResponse.json({ message: 'Ce lien de réinitialisation est expiré.' }, { status: 422 }),
      ),
    )

    await userEvent.type(screen.getByLabelText(/^Nouveau mot de passe/), 'Tricolis!2026')
    await userEvent.type(screen.getByLabelText(/^Confirmation/), 'Tricolis!2026')
    await userEvent.click(screen.getByRole('button', { name: /Enregistrer/ }))

    expect(await screen.findByText(/Ce lien de réinitialisation est expiré/)).toBeInTheDocument()
  })
})
