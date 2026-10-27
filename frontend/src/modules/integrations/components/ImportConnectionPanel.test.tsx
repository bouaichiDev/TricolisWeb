import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import type { ImportConnectionState } from '../types/importConnection'
import { ImportConnectionPanel } from './ImportConnectionPanel'

const ID = '01JQZ00000000000000IMPORT1'

const state = (overrides: Partial<ImportConnectionState> = {}): ImportConnectionState => ({
  channel: 'rest_pull',
  frequency: 'hourly',
  orderSource: 'rest_api',
  connection: { kind: 'rest', configured: false, hasCredentials: false, code: `customer-import-${ID}` },
  mapping: { mapped: ['externalReference'], unknownTargets: [], missingRequired: ['orderDate'], problems: [] },
  flags: { inactive: false, mappingMissing: false, mappingIncomplete: true, connectionMissing: true, credentialMissing: false, testable: false },
  ready: false,
  ...overrides,
})

function render(initial: ImportConnectionState) {
  server.use(http.get(`${API}/customer-import-configurations/${ID}/connection`, () => HttpResponse.json({ data: initial })))
  renderWithProviders(<ImportConnectionPanel configurationId={ID} />, {
    membership: withPermissions(['customer_import_configurations.view', 'customer_import_configurations.update']),
  })
}

describe('connexion d’un import', () => {
  it('dit ce qui manque, et ne propose ni test ni passe tant que rien n’est prêt', async () => {
    render(state())

    expect(await screen.findByText('Connexion non configurée')).toBeInTheDocument()
    expect(screen.getByText('Correspondance incomplète : orderDate')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Tester la connexion/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: /Exécuter maintenant/ })).toBeDisabled()
  })

  it('enregistre la connexion : le secret part une fois, puis le champ se vide', async () => {
    let body: Record<string, unknown> | null = null
    server.use(
      http.put(`${API}/customer-import-configurations/${ID}/connection`, async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: state({ connection: { kind: 'rest', configured: true, hasCredentials: true, code: 'c', baseUrl: 'https://api.partner.example.com', authType: 'bearer' }, flags: { ...state().flags, connectionMissing: false, testable: true } }) })
      }),
    )
    render(state())

    await userEvent.type(await screen.findByLabelText('Adresse de base (HTTPS)'), 'https://api.partner.example.com')
    await userEvent.type(screen.getByLabelText('Secret'), 'jeton-secret')
    await userEvent.type(screen.getByLabelText('Chemin des commandes'), '/v1/orders')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer la connexion' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({ baseUrl: 'https://api.partner.example.com', authType: 'bearer', credentials: 'jeton-secret', settings: { path: '/v1/orders' } })
    await waitFor(() => expect(screen.getByLabelText('Secret')).toHaveValue(''))
  })

  it('montre le verdict réel du test et met une passe en file', async () => {
    server.use(
      http.post(`${API}/customer-import-configurations/${ID}/connection/test`, () => HttpResponse.json({ data: { ok: false, message: 'Le système client a répondu 401.', details: {} } })),
      http.post(`${API}/customer-import-configurations/${ID}/run`, () => HttpResponse.json({ data: { queued: true } }, { status: 202 })),
    )
    render(state({ connection: { kind: 'rest', configured: true, hasCredentials: true, code: 'c', baseUrl: 'https://api.partner.example.com', authType: 'bearer' }, flags: { ...state().flags, mappingIncomplete: false, connectionMissing: false, testable: true }, ready: true }))

    await userEvent.click(await screen.findByRole('button', { name: /Tester la connexion/ }))
    expect(await screen.findByText('Échec du test : Le système client a répondu 401.')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Exécuter maintenant/ }))
    expect(await screen.findByText(/Passe mise en file/)).toBeInTheDocument()
  })

  it('règle une relève FTP/SFTP ici, mot de passe en écriture seule', async () => {
    const file = state({
      channel: 'sftp',
      orderSource: 'xml_import',
      connection: { kind: 'file', configured: true, hasCredentials: true, driver: 'sftp', host: 'sftp.partner.example.com', port: 22, username: 'tricolis', directory: 'outbox', filePattern: '*.xml', afterImport: 'archive', archiveDirectory: 'outbox/archive', actorEmail: null },
      flags: { ...state().flags, connectionMissing: false, testable: true },
    })
    let sent: Record<string, unknown> | null = null
    server.use(http.put(`${API}/customer-import-configurations/${ID}/connection`, async ({ request }) => {
      sent = (await request.json()) as Record<string, unknown>
      return HttpResponse.json({ data: file })
    }))
    render(file)

    expect(await screen.findByText('sftp://sftp.partner.example.com:22')).toBeInTheDocument()
    expect(screen.getByText('Archivé dans outbox/archive')).toBeInTheDocument()
    expect(screen.getByLabelText('Mot de passe')).toHaveValue('')
    expect(screen.getByText(/Un mot de passe est enregistré/)).toBeInTheDocument()

    await userEvent.clear(screen.getByLabelText('Répertoire'))
    await userEvent.type(screen.getByLabelText('Répertoire'), 'inbox')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer la connexion' }))

    await waitFor(() => expect(sent).toMatchObject({ host: 'sftp.partner.example.com', port: 22, directory: 'inbox' }))
    expect(sent).not.toHaveProperty('password')
  })
})
