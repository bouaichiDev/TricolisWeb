import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { CustomerApiConfigurationDetailPage } from './CustomerApiConfigurationDetailPage'
import { API_CONFIG_ID, apiConfiguration, apiKeyIssued } from '../testSupport'

const ROTATED_KEY = 'trk_live_ffffffffffffffff'

function serve(overrides: Record<string, unknown> = {}) {
  server.use(
    http.get(`${API}/customer-api-configurations/${API_CONFIG_ID}`, () =>
      HttpResponse.json({ data: apiConfiguration(overrides), meta: [] }),
    ),
  )
}

const render = (
  permissions: string[] = [
    'customer_api_configurations.view',
    'customer_api_configurations.rotate_key',
  ],
) =>
  renderWithProviders(<CustomerApiConfigurationDetailPage />, {
    membership: withPermissions(permissions),
    route: `/integrations/api-access/${API_CONFIG_ID}`,
    routePath: '/integrations/api-access/:id',
  })

describe('fiche d’un accès API client', () => {
  beforeEach(() => {
    Object.assign(navigator, {
      clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    })
  })

  it('affiche les restrictions et la dernière utilisation', async () => {
    serve()
    render()

    expect(await screen.findByRole('heading', { name: 'Portail client' })).toBeInTheDocument()
    expect(screen.getByText('10.0.0.0/24')).toBeInTheDocument()
    expect(screen.getByText('orders.view')).toBeInTheDocument()
  })

  /**
   * Le §23 : ni la clé, ni son empreinte. La ressource ne renvoie pas
   * `apiKeyHash`, et l'écran n'en affiche aucune trace, même masquée.
   */
  it('n’affiche jamais la clé ni son empreinte', async () => {
    serve()
    render()

    await screen.findByRole('heading', { name: 'Portail client' })

    expect(screen.queryByText(/apiKeyHash/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/trk_live/)).not.toBeInTheDocument()
    expect(screen.getByText(/n’est pas consultable/)).toBeInTheDocument()
  })

  /** `lastUsedAt` se lit, ne se règle pas : aucun champ de saisie (§28). */
  it('présente la dernière utilisation en lecture seule', async () => {
    serve()
    render()

    await screen.findByRole('heading', { name: 'Portail client' })

    expect(screen.getByText('Dernière utilisation')).toBeInTheDocument()
    expect(screen.queryByLabelText('Dernière utilisation')).not.toBeInTheDocument()
  })

  it('dit qu’une clé jamais employée ne l’a jamais été', async () => {
    serve({ lastUsedAt: null })
    render()

    expect(await screen.findByText('Jamais utilisée')).toBeInTheDocument()
  })

  /**
   * La rotation invalide l'ancienne clé sur-le-champ : elle est confirmée, et
   * la nouvelle clé suit le même chemin que celle de la création — affichée une
   * fois, jamais relisible.
   */
  it('prévient avant de renouveler, puis montre la nouvelle clé une fois', async () => {
    serve()
    server.use(
      http.post(`${API}/customer-api-configurations/${API_CONFIG_ID}/rotate-key`, () =>
        HttpResponse.json({ data: apiKeyIssued(ROTATED_KEY), meta: [] }),
      ),
    )

    render()
    await screen.findByRole('heading', { name: 'Portail client' })

    await userEvent.click(screen.getByRole('button', { name: /Renouveler la clé/ }))

    // `ConfirmDialog` est un dialogue ordinaire : la confirmation se reconnaît
    // à son avertissement, pas à un rôle distinct.
    const confirm = within(await screen.findByRole('dialog'))
    expect(confirm.getByText(/immédiatement invalidée/)).toBeInTheDocument()
    await userEvent.click(confirm.getByRole('button', { name: /Renouveler la clé/ }))

    expect(await screen.findByText(ROTATED_KEY)).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /J’ai copié la clé/ }))
    await waitFor(() => expect(screen.queryByText(ROTATED_KEY)).not.toBeInTheDocument())
  })

  it('masque la rotation sans la permission', async () => {
    serve()
    render(['customer_api_configurations.view'])

    await screen.findByRole('heading', { name: 'Portail client' })
    expect(screen.queryByRole('button', { name: /Renouveler la clé/ })).not.toBeInTheDocument()
  })
})
