import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { ApiConfigurationListPage } from './ApiConfigurationListPage'

const CONFIG_ID = '01JQZ0000000000000APIC01'

const configuration = (overrides: Record<string, unknown> = {}) => ({
  id: CONFIG_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  code: 'driver_position',
  name: 'Position chauffeur',
  baseUrl: 'https://telematique.example.test',
  authType: 'bearer',
  hasCredentials: true,
  headers: null,
  timeoutSeconds: 10,
  settings: null,
  isActive: true,
  lastUsedAt: null,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
  ...overrides,
})

function render(permissions: string[], items: unknown[] = [configuration()]) {
  server.use(http.get(`${API}/api-configurations`, () => HttpResponse.json(paginated(items))))

  return renderWithProviders(<ApiConfigurationListPage />, {
    membership: withPermissions(permissions),
  })
}

describe('API externes', () => {
  it('liste les API avec leur adresse et leur authentification', async () => {
    render(['api_configurations.view'])

    expect(await screen.findByText('Position chauffeur')).toBeInTheDocument()
    expect(screen.getByText('https://telematique.example.test')).toBeInTheDocument()
    expect(screen.getByText('Jeton Bearer')).toBeInTheDocument()
    expect(screen.getByText('Jamais appelée')).toBeInTheDocument()
  })

  /**
   * Le secret ne se relit jamais : le champ est vide en modification, et le
   * laisser tel quel conserve celui en place.
   */
  it('n’affiche jamais le secret et ne l’envoie pas s’il n’est pas retapé', async () => {
    let body: unknown = null
    render(['api_configurations.view', 'api_configurations.update'])

    server.use(
      http.patch(`${API}/api-configurations/${CONFIG_ID}`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: configuration(), meta: [] })
      }),
    )

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier' }))

    const dialog = within(await screen.findByRole('dialog'))
    const secret = dialog.getByLabelText(/^Secret/)

    expect(secret).toHaveValue('')
    expect(secret).toHaveAttribute('placeholder', expect.stringContaining('Inchangé'))

    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    // Champ laisse vide : le secret en place n'est ni efface ni reenvoye.
    expect(body).not.toHaveProperty('credentials')
  })

  it('envoie le secret quand il est saisi', async () => {
    let body: unknown = null
    render(['api_configurations.view', 'api_configurations.create'])

    server.use(
      http.post(`${API}/api-configurations`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: configuration(), meta: [] }, { status: 201 })
      }),
    )

    await userEvent.click(await screen.findByRole('button', { name: /Déclarer une API/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(dialog.getByLabelText(/^Code/), 'driver_position')
    await userEvent.type(dialog.getByLabelText(/^Nom/), 'Position chauffeur')
    await userEvent.type(dialog.getByLabelText(/^Adresse/), 'telematique.example.test')

    // Le secret n'apparait qu'une fois l'authentification choisie.
    await userEvent.click(dialog.getByLabelText(/^Authentification/))
    await userEvent.click(await screen.findByRole('option', { name: 'Jeton Bearer' }))
    await userEvent.type(dialog.getByLabelText(/^Secret/), 'jeton-secret')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({ code: 'driver_position', credentials: 'jeton-secret' })
  })

  /** Sans authentification, aucun secret n'est demandé. */
  it('ne demande pas de secret quand l’API n’en veut pas', async () => {
    render(['api_configurations.view', 'api_configurations.create'])

    await userEvent.click(await screen.findByRole('button', { name: /Déclarer une API/ }))

    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.queryByLabelText(/^Secret/)).not.toBeInTheDocument()
  })

  /**
   * Le chemin porte le canal de l'organisme, fixe ; le gabarit porte la
   * reference de la course, variable. Les confondre n'interroge rien : le test
   * verifie que les deux partent bien distincts.
   */
  it('enregistre la description de l’appel', async () => {
    let body: unknown = null
    render(['api_configurations.view', 'api_configurations.update'])

    server.use(
      http.patch(`${API}/api-configurations/${CONFIG_ID}`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: configuration(), meta: [] })
      }),
    )

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier' }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(dialog.getByLabelText(/^Chemin/), '/gw/channels/1234371/messages')
    await userEvent.type(dialog.getByLabelText(/^Paramètre de requête/), 'data')
    // `{{` tape une accolade litterale : userEvent lirait sinon `{reference}`
    // comme une touche a presser.
    await userEvent.type(
      dialog.getByLabelText(/^Gabarit du filtre/),
      '{{"filter":"Planid={{reference}"}',
    )
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect((body as { settings: unknown }).settings).toEqual({
      path: '/gw/channels/1234371/messages',
      queryKey: 'data',
      queryTemplate: '{"filter":"Planid={reference}"}',
    })
  })

  /** Champs laisses vides : rien a construire, donc `null` plutot qu'une adresse tronquee. */
  it('envoie null quand l’appel n’est pas décrit', async () => {
    let body: unknown = null
    render(['api_configurations.view', 'api_configurations.update'])

    server.use(
      http.patch(`${API}/api-configurations/${CONFIG_ID}`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: configuration(), meta: [] })
      }),
    )

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier' }))
    await userEvent.click(
      within(await screen.findByRole('dialog')).getByRole('button', { name: 'Enregistrer' }),
    )

    await waitFor(() => expect(body).not.toBeNull())
    expect((body as { settings: unknown }).settings).toEqual({ path: null, queryKey: null, queryTemplate: null })
  })

  it('masque création, modification et suppression sans les permissions', async () => {
    render(['api_configurations.view'])

    await screen.findByText('Position chauffeur')

    expect(screen.queryByRole('button', { name: /Déclarer une API/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Modifier' })).not.toBeInTheDocument()
  })
})
