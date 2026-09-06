import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { AccessRequestPage } from './AccessRequestPage'

function render(
  respond: () => Response = () =>
    HttpResponse.json({ data: { message: 'ok' }, meta: [] }, { status: 201 }),
) {
  const bodies: Record<string, unknown>[] = []

  server.use(
    http.post(`${API}/access-requests`, async ({ request }) => {
      bodies.push((await request.json()) as Record<string, unknown>)

      return respond()
    }),
  )

  renderWithProviders(<AccessRequestPage />, { route: '/request-access' })

  return bodies
}

async function fill() {
  await userEvent.type(screen.getByLabelText('Société'), 'Transports Atlas')
  await userEvent.type(screen.getByLabelText('Nom du contact'), 'Sara Bennani')
  await userEvent.type(screen.getByLabelText('Adresse e-mail'), 'sara@atlas.example')
  await userEvent.type(screen.getByLabelText('Téléphone'), '+212600000000')
}

/**
 * Le formulaire est rempli par quelqu'un qui n'a pas de compte — c'est bien
 * pour cela qu'il en demande un. Rien n'est créé ici : la plateforme décide.
 */
describe('demander un accès entreprise', () => {
  it('transmet les coordonnées et annonce une vérification, pas un compte', async () => {
    const bodies = render()

    await fill()
    await userEvent.click(screen.getByRole('button', { name: /Envoyer ma demande/ }))

    await waitFor(() => expect(bodies).toHaveLength(1))
    expect(bodies[0]).toMatchObject({
      companyName: 'Transports Atlas',
      contactName: 'Sara Bennani',
      email: 'sara@atlas.example',
      phone: '+212600000000',
    })

    expect(await screen.findByText(/aucun compte n’est créé avant/)).toBeInTheDocument()
  })

  /**
   * Le téléphone est le seul moyen de vérifier qu'une société existe : une
   * adresse de courriel se crée en trente secondes.
   */
  it('exige le téléphone', async () => {
    const bodies = render()

    await userEvent.type(screen.getByLabelText('Société'), 'Transports Atlas')
    await userEvent.type(screen.getByLabelText('Nom du contact'), 'Sara Bennani')
    await userEvent.type(screen.getByLabelText('Adresse e-mail'), 'sara@atlas.example')
    await userEvent.click(screen.getByRole('button', { name: /Envoyer ma demande/ }))

    await waitFor(() => expect(bodies).toHaveLength(0))
    expect(screen.getByLabelText('Téléphone')).toHaveAttribute('aria-invalid', 'true')
  })

  it('montre le refus du serveur plutôt qu’un formulaire vidé', async () => {
    render(() =>
      HttpResponse.json(
        { message: 'Une demande est déjà en cours pour cette adresse.' },
        { status: 422 },
      ),
    )

    await fill()
    await userEvent.click(screen.getByRole('button', { name: /Envoyer ma demande/ }))

    expect(await screen.findByText(/déjà en cours/)).toBeInTheDocument()
    expect(screen.getByLabelText('Société')).toHaveValue('Transports Atlas')
  })
})
