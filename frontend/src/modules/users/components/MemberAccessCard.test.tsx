import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { MemberAccessCard } from './MemberAccessCard'
import type { Member } from '../types/member'

const MEMBER_ID = '01JQZ0000000000000MEMB01'

const member = {
  id: MEMBER_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  userId: '01JQZ00000000000000USER1',
  user: { id: '01JQZ00000000000000USER1', firstName: 'Alex', lastName: 'Nalex', email: 'alex@mail.com' },
  roles: [],
  status: 'active',
  isOwner: false,
  isPrimary: false,
} as unknown as Member

function render(permissions: string[] = ['users.reset_password']) {
  const calls: { path: string; body: unknown }[] = []

  server.use(
    http.post(`${API}/organization-users/${MEMBER_ID}/password-reset-link`, () => {
      calls.push({ path: 'link', body: null })

      return HttpResponse.json({ data: { email: 'alex@mail.com' }, meta: [] })
    }),
    http.put(`${API}/organization-users/${MEMBER_ID}/password`, async ({ request }) => {
      calls.push({ path: 'password', body: await request.json() })

      return new HttpResponse(null, { status: 204 })
    }),
  )

  renderWithProviders(<MemberAccessCard member={member} />, {
    membership: withPermissions(permissions),
  })

  return calls
}

describe('rendre l’accès à un membre', () => {
  /**
   * Renommer un compte et pouvoir entrer dedans ne sont pas le même pouvoir :
   * la carte ne doit pas apparaître pour qui n'a que `users.update`.
   */
  it('reste invisible sans la permission dédiée', () => {
    render(['users.update'])

    expect(screen.queryByRole('button', { name: /lien de réinitialisation/ })).not.toBeInTheDocument()
  })

  it('annonce l’adresse servie avant d’envoyer', () => {
    render()

    expect(screen.getByText(/Le lien partira à alex@mail.com/)).toBeInTheDocument()
  })

  /** Un envoi de courriel ne se déclenche pas par un clic isolé. */
  it('demande confirmation avant d’envoyer le lien', async () => {
    const calls = render()

    await userEvent.click(screen.getByRole('button', { name: /Envoyer un lien/ }))

    expect(await screen.findByText(/Envoyer le lien de réinitialisation \?/)).toBeInTheDocument()
    expect(calls).toHaveLength(0)

    await userEvent.click(screen.getByRole('button', { name: /^Envoyer un lien/ }))

    await waitFor(() => expect(calls.map((call) => call.path)).toEqual(['link']))
  })

  it('envoie le mot de passe saisi, confirmé', async () => {
    const calls = render()

    await userEvent.click(screen.getByRole('button', { name: /Définir un mot de passe/ }))

    await userEvent.type(await screen.findByLabelText(/^Nouveau mot de passe/), 'Tricolis!2026')
    await userEvent.type(screen.getByLabelText(/^Confirmation/), 'Tricolis!2026')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(calls).toHaveLength(1))
    expect(calls[0].body).toMatchObject({
      password: 'Tricolis!2026',
      password_confirmation: 'Tricolis!2026',
    })
  })

  /**
   * L'administrateur ne relira pas le mot de passe une fois posé : une coquille
   * enfermerait le membre dehors sans que personne ne sache pourquoi.
   */
  it('bloque l’enregistrement tant que les deux saisies diffèrent', async () => {
    render()

    await userEvent.click(screen.getByRole('button', { name: /Définir un mot de passe/ }))

    await userEvent.type(await screen.findByLabelText(/^Nouveau mot de passe/), 'Tricolis!2026')
    await userEvent.type(screen.getByLabelText(/^Confirmation/), 'Tricolis!2027')

    expect(await screen.findByText('Les deux saisies diffèrent.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled()
  })

  /** Un chauffeur déconnecté en pleine tournée sans qu'on l'ait dit surprendrait. */
  it('prévient que les sessions ouvertes tombent', async () => {
    render()

    await userEvent.click(screen.getByRole('button', { name: /Définir un mot de passe/ }))

    expect(await screen.findByText(/sessions ouvertes du membre seront fermées/)).toBeInTheDocument()
  })
})
