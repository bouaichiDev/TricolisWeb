import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { session } from '@/shared/api/session'
import { makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

async function fillAndSubmit() {
  const user = userEvent.setup()
  await user.type(await screen.findByLabelText('Email'), 'admin@ikea.test')
  await user.type(screen.getByLabelText('Mot de passe'), 'password')
  await user.click(screen.getByRole('button', { name: 'Se connecter' }))

  return user
}

describe('LoginPage', () => {
  it('stores the portal token and opens the dashboard', async () => {
    server.use(
      http.post(`${API}/auth/login`, () =>
        HttpResponse.json({ data: { token: 'portal-token', context: makeContext() }, meta: {} }),
      ),
    )

    renderApp('/login')
    await fillAndSubmit()

    expect(await screen.findByRole('heading', { name: /Bonjour Ingrid/ })).toBeInTheDocument()
    expect(session.getToken()).toBe('portal-token')
  })

  it('shows the backend refusal', async () => {
    server.use(
      http.post(`${API}/auth/login`, () =>
        HttpResponse.json(
          { message: 'Invalid.', errors: { email: ['Ces identifiants ne correspondent pas.'] } },
          { status: 422 },
        ),
      ),
    )

    renderApp('/login')
    await fillAndSubmit()

    expect(await screen.findByRole('alert')).toHaveTextContent('Ces identifiants ne correspondent pas.')
  })

  it('lets a member of several customers pick one', async () => {
    let chosen: unknown = null
    server.use(
      http.post(`${API}/auth/login`, async ({ request }) => {
        const body = (await request.json()) as { customerId?: string }

        if (!body.customerId) {
          return HttpResponse.json(
            {
              message: 'Choisissez.',
              code: 'customer_selection_required',
              customers: [
                { id: '01IKEA', name: 'IKEA' },
                { id: '01QOQA', name: 'QOQA' },
              ],
            },
            { status: 409 },
          )
        }

        chosen = body.customerId

        return HttpResponse.json({ data: { token: 't', context: makeContext() }, meta: {} })
      }),
    )

    renderApp('/login')
    const user = await fillAndSubmit()
    await user.click(await screen.findByRole('button', { name: 'QOQA' }))

    await screen.findByRole('heading', { name: /Bonjour/ })
    expect(chosen).toBe('01QOQA')
  })
})
