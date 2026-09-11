import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { platformMembership, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StatusDefaultsPanel } from './StatusDefaultsPanel'
import type { StatusDefault } from '../types/status'

const ROWS: StatusDefault[] = [
  {
    source: 'order',
    statusId: null,
    code: null,
    systemDefault: 'draft',
    options: [
      { id: 'S-DRAFT', code: 'draft', label: 'Brouillon', active: true },
      { id: 'S-CONF', code: 'confirmed', label: 'Confirmée', active: true },
      { id: 'S-OLD', code: 'archived', label: 'Archivée', active: false },
    ],
  },
  {
    source: 'claim',
    statusId: 'S-CREATED',
    code: 'created',
    systemDefault: null,
    options: [{ id: 'S-CREATED', code: 'created', label: 'Créée', active: true }],
  },
]

function serve() {
  const puts: { source: string; body: unknown }[] = []

  server.use(
    http.get(`${API}/statuses/defaults`, () => HttpResponse.json({ data: ROWS, meta: [] })),
    http.put(`${API}/statuses/defaults/:source`, async ({ params, request }) => {
      puts.push({ source: String(params.source), body: await request.json() })
      return HttpResponse.json({ data: ROWS, meta: [] })
    }),
  )

  return puts
}

const platform = platformMembership({ permissions: [{ id: 'p-1', code: 'statuses.update' }] })

const rowOf = async (entity: string) =>
  (await screen.findByText(entity)).closest('li') as HTMLElement

describe('StatusDefaultsPanel', () => {
  /** La liste vient du serveur : une entité ajoutée au code apparaît sans rien toucher ici. */
  it('liste chaque entité avec son statut par défaut, ou la valeur de la base', async () => {
    serve()
    renderWithProviders(<StatusDefaultsPanel />, {
      membership: platform,
    })

    expect(within(await rowOf('Commande')).getByText('Valeur de la base : draft')).toBeInTheDocument()
    expect(within(await rowOf('Réclamation')).getByText('Créée')).toBeInTheDocument()
  })

  it('enregistre aussitôt le statut choisi, sans proposer un statut désactivé', async () => {
    const puts = serve()
    renderWithProviders(<StatusDefaultsPanel />, {
      membership: platform,
    })

    await userEvent.click(within(await rowOf('Commande')).getByRole('combobox'))

    expect(screen.queryByRole('option', { name: /Archivée/ })).not.toBeInTheDocument()
    await userEvent.click(await screen.findByRole('option', { name: /Confirmée/ }))

    await waitFor(() => expect(puts).toEqual([{ source: 'order', body: { statusId: 'S-CONF' } }]))
  })

  it('retire le choix en revenant à la valeur de la base', async () => {
    const puts = serve()
    renderWithProviders(<StatusDefaultsPanel />, {
      membership: platform,
    })

    await userEvent.click(within(await rowOf('Réclamation')).getByRole('combobox'))
    await userEvent.click(await screen.findByRole('option', { name: /Aucun/ }))

    await waitFor(() => expect(puts).toEqual([{ source: 'claim', body: { statusId: null } }]))
  })

  /** Un administrateur d'organisme lit le réglage ; seule la plateforme le change. */
  it('laisse lire sans permettre de changer', async () => {
    serve()
    renderWithProviders(<StatusDefaultsPanel />, {
      membership: withPermissions(['statuses.view']),
    })

    expect(within(await rowOf('Commande')).getByRole('combobox')).toBeDisabled()
  })
})
