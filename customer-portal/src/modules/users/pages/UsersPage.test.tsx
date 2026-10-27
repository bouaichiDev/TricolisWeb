import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'

import { session } from '@/shared/api/session'
import { ADMIN_PERMISSIONS, makeContext, makeUser } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

const page = (users = [makeUser()]) =>
  HttpResponse.json({ data: users, meta: { currentPage: 1, perPage: 25, total: users.length, lastPage: 1 } })

describe('UsersPage', () => {
  it('lists the members and offers the invitation to an administrator', async () => {
    server.use(http.get(`${API}/users`, () => page()))

    renderApp('/users', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByRole('link', { name: 'Vera Lecture' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Inviter/ })).toBeInTheDocument()
  })

  it('hides the invitation without customer.users.invite', async () => {
    server.use(http.get(`${API}/users`, () => page()))

    renderApp('/users', makeContext(['customer.users.view']))

    await screen.findByRole('link', { name: 'Vera Lecture' })
    expect(screen.queryByRole('button', { name: /Inviter/ })).not.toBeInTheDocument()
  })

  it('presents a member of another customer as not found', async () => {
    server.use(
      http.get(`${API}/users/:id`, () => HttpResponse.json({ message: 'Ressource introuvable.' }, { status: 404 })),
    )

    renderApp('/users/01QOQAMEMBER', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByText('Utilisateur introuvable')).toBeInTheDocument()
  })

  it('drops the session when the backend revokes portal access', async () => {
    server.use(
      http.get(`${API}/users`, () =>
        HttpResponse.json({ message: 'Désactivé.', code: 'customer_access_revoked' }, { status: 403 }),
      ),
    )

    renderApp('/users', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByRole('heading', { name: /Connexion/ })).toBeInTheDocument()
    expect(session.getToken()).toBeNull()
  })
})
