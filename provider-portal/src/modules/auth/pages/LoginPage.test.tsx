import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { session } from '@/shared/api/session'
import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

async function fillAndSubmit() {
  const user = userEvent.setup()
  await user.type(await screen.findByLabelText('Email'), 'admin@transports-alpha.test')
  await user.type(screen.getByLabelText('Mot de passe'), 'password')
  await user.click(screen.getByRole('button', { name: 'Se connecter' }))

  return user
}

describe('LoginPage', () => {
  it('garde le jeton du portail et ouvre le tableau de bord', async () => {
    server.use(
      http.post(`${API}/auth/login`, () =>
        HttpResponse.json({ data: { token: 'portal-token', context: makeContext(ADMIN_PERMISSIONS) }, meta: {} }),
      ),
    )

    renderApp('/login')
    await fillAndSubmit()

    expect(await screen.findByRole('heading', { name: /Bonjour Alice/ })).toBeInTheDocument()
    expect(session.getToken()).toBe('portal-token')
    expect(localStorage.length).toBe(0)
  })

  it('montre le refus du serveur', async () => {
    server.use(
      http.post(`${API}/auth/login`, () =>
        HttpResponse.json({ message: 'Invalid.', errors: { email: ['Adresse e-mail ou mot de passe incorrect.'] } }, { status: 422 }),
      ),
    )

    renderApp('/login')
    await fillAndSubmit()

    expect(await screen.findByRole('alert')).toHaveTextContent('Adresse e-mail ou mot de passe incorrect.')
    expect(session.getToken()).toBeNull()
  })

  it('fait choisir le fournisseur quand le compte en sert plusieurs', async () => {
    let chosen: unknown = null
    server.use(
      http.post(`${API}/auth/login`, async ({ request }) => {
        const body = (await request.json()) as { providerId?: string }

        if (!body.providerId) {
          return HttpResponse.json(
            {
              message: 'Choisissez.',
              code: 'provider_selection_required',
              providers: [
                { id: '01ALPHA', name: 'Transports Alpha' },
                { id: '01BETA', name: 'Transports Beta' },
              ],
            },
            { status: 409 },
          )
        }

        chosen = body.providerId

        return HttpResponse.json({ data: { token: 't', context: makeContext(ADMIN_PERMISSIONS) }, meta: {} })
      }),
    )

    renderApp('/login')
    const user = await fillAndSubmit()
    await user.click(await screen.findByRole('button', { name: 'Transports Beta' }))

    await screen.findByRole('heading', { name: /Bonjour/ })
    expect(chosen).toBe('01BETA')
  })
})
