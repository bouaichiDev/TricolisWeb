import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

const profile = {
  provider: {
    code: 'transports-alpha',
    name: 'Transports Alpha',
    status: 'active',
    address: { line1: 'Rue du Port 4', line2: null, postalCode: '1201', city: 'Genève', country: 'CH' },
    contact: null,
  },
  user: { firstName: 'Alice', lastName: 'Admin', email: 'admin@transports-alpha.test', phone: null, preferredLanguage: 'fr' },
}

describe('ProfilePage', () => {
  it('montre la fiche du fournisseur en lecture seule', async () => {
    server.use(http.get(`${API}/profile`, () => HttpResponse.json({ data: profile, meta: {} })))

    renderApp('/profile', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByText('Rue du Port 4 · 1201 Genève')).toBeInTheDocument()
    expect(screen.getByText('transports-alpha')).toBeInTheDocument()
    expect(screen.queryByRole('textbox', { name: /Nom du fournisseur/ })).not.toBeInTheDocument()
  })

  it('change le mot de passe avec l’ancien', async () => {
    let body: unknown = null
    server.use(
      http.get(`${API}/profile`, () => HttpResponse.json({ data: profile, meta: {} })),
      http.put(`${API}/profile/password`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: { message: 'Votre mot de passe est modifié.' }, meta: {} })
      }),
    )
    renderApp('/profile', makeContext(ADMIN_PERMISSIONS))
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Mot de passe actuel'), 'password')
    await user.type(screen.getByLabelText('Nouveau mot de passe'), 'Motdepasse-Solide-2026!')
    await user.type(screen.getByLabelText('Confirmer le mot de passe'), 'Motdepasse-Solide-2026!')
    await user.click(screen.getByRole('button', { name: 'Changer le mot de passe' }))

    await waitFor(() =>
      expect(body).toEqual({ currentPassword: 'password', password: 'Motdepasse-Solide-2026!', password_confirmation: 'Motdepasse-Solide-2026!' }),
    )
    expect(await screen.findByText('Votre mot de passe est modifié.')).toBeInTheDocument()
  })
})
