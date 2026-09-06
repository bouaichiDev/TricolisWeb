import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { ForgotPasswordPage } from './ForgotPasswordPage'

function render() {
  const bodies: Record<string, unknown>[] = []

  server.use(
    http.post(`${API}/auth/forgot-password`, async ({ request }) => {
      bodies.push((await request.json()) as Record<string, unknown>)

      return HttpResponse.json({ data: { message: 'ok' }, meta: [] })
    }),
  )

  renderWithProviders(<ForgotPasswordPage />, { route: '/forgot-password' })

  return bodies
}

/**
 * Réservé aux administrateurs, et l'écran le dit avant la saisie. Il ne dit en
 * revanche jamais si l'adresse est connue — un formulaire qui répondrait
 * « adresse inconnue » serait un annuaire.
 */
describe('mot de passe oublié', () => {
  it('avertit que le formulaire est réservé aux administrateurs', () => {
    render()

    expect(screen.getByText(/Réservé aux comptes administrateurs/)).toBeInTheDocument()
    expect(screen.getByText(/demandent la réinitialisation à l’administrateur/)).toBeInTheDocument()
  })

  it('demande le lien et répond au conditionnel', async () => {
    const bodies = render()

    await userEvent.type(screen.getByLabelText('Adresse e-mail'), 'sara@atlas.example')
    await userEvent.click(screen.getByRole('button', { name: /Envoyer le lien/ }))

    await waitFor(() => expect(bodies).toHaveLength(1))
    expect(bodies[0]).toEqual({ email: 'sara@atlas.example' })
    expect(await screen.findByText(/Si un compte administrateur existe/)).toBeInTheDocument()
  })

  it('ne part pas sans adresse valable', async () => {
    const bodies = render()

    await userEvent.type(screen.getByLabelText('Adresse e-mail'), 'pas-une-adresse')
    await userEvent.click(screen.getByRole('button', { name: /Envoyer le lien/ }))

    await waitFor(() => expect(bodies).toHaveLength(0))
  })
})
