import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { MailConfigurationPage } from './MailConfigurationPage'

const saved = {
  id: '01JQZ0000000000000MAIL01',
  organizationId: '01JQZ0000000000000000ORG1',
  host: 'smtp.atlas.ch',
  port: 587,
  encryption: 'tls',
  username: 'envoi@atlas.ch',
  hasPassword: true,
  fromAddress: 'contact@atlas.ch',
  fromName: 'Atlas Transport',
  replyTo: null,
  isActive: true,
  lastUsedAt: null,
  createdAt: '2026-09-01T10:00:00.000000Z',
  updatedAt: '2026-09-01T10:00:00.000000Z',
}

function render(configuration: typeof saved | null) {
  const sent: Record<string, unknown>[] = []

  server.use(
    http.get(`${API}/mail-configuration`, () =>
      HttpResponse.json({ data: configuration, meta: [] }),
    ),
    http.put(`${API}/mail-configuration`, async ({ request }) => {
      sent.push((await request.json()) as Record<string, unknown>)

      return HttpResponse.json({ data: saved, meta: [] })
    }),
  )

  renderWithProviders(<MailConfigurationPage />, {
    membership: withPermissions([
      'mail_configuration.view',
      'mail_configuration.update',
      'mail_configuration.delete',
    ]),
  })

  return sent
}

describe('la messagerie d’envoi', () => {
  /**
   * Tant qu'aucune boîte n'est réglée, l'organisation envoie avec celle du
   * serveur : activer la fonctionnalité ne doit couper personne, et l'écran
   * doit le dire plutôt que de laisser croire à une panne.
   */
  it('annonce le repli sur la messagerie du serveur', async () => {
    render(null)

    expect(await screen.findByText(/Aucune boîte n’est réglée/)).toBeInTheDocument()
  })

  it('sépare le compte qui s’authentifie de l’adresse affichée', async () => {
    render(saved)

    expect(await screen.findByLabelText(/^Identifiant/)).toHaveValue('envoi@atlas.ch')
    expect(screen.getByLabelText(/^Adresse d’expédition/)).toHaveValue('contact@atlas.ch')
  })

  /**
   * Rouvrir l'écran pour changer un port ne doit pas obliger à ressaisir un
   * secret qu'on n'a plus sous la main.
   */
  it('conserve le mot de passe quand le champ reste vide', async () => {
    const sent = render(saved)

    expect(await screen.findByText(/Un mot de passe est enregistré/)).toBeInTheDocument()

    await userEvent.clear(screen.getByLabelText(/^Port/))
    await userEvent.type(screen.getByLabelText(/^Port/), '465')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).not.toHaveProperty('password')
    expect(sent[0]).toMatchObject({ port: 465, host: 'smtp.atlas.ch' })
  })

  it('envoie le mot de passe quand on en saisit un', async () => {
    const sent = render(saved)

    await userEvent.type(await screen.findByLabelText(/^Mot de passe/), 'nouveau-secret')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toMatchObject({ password: 'nouveau-secret' })
  })

  /**
   * « 535 authentification refusée » se cherche dans une documentation ;
   * « l'essai a échoué » nulle part. Le message distant est rendu en entier.
   */
  it('montre l’erreur du serveur distant en entier', async () => {
    render(saved)

    server.use(
      http.post(`${API}/mail-configuration/test`, () =>
        HttpResponse.json(
          {
            message: 'L’essai d’envoi a échoué.',
            errors: { recipient: ['535 5.7.8 Authentication credentials invalid'] },
          },
          { status: 422 },
        ),
      ),
    )

    await userEvent.click(await screen.findByRole('button', { name: /Envoyer un essai/ }))

    expect(await screen.findByText(/535 5.7.8 Authentication credentials invalid/)).toBeInTheDocument()
  })

  /** L'essai n'a rien à éprouver tant qu'aucune boîte n'existe. */
  it('n’offre pas l’essai sans configuration', async () => {
    render(null)

    await screen.findByText(/Aucune boîte n’est réglée/)

    expect(screen.queryByRole('button', { name: /Envoyer un essai/ })).not.toBeInTheDocument()
  })
})
