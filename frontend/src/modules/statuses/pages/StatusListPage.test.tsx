import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, platformMembership, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StatusListPage } from './StatusListPage'

const status = {
  id: '01JQZ0000000000000STAT01',
  source: 'order',
  status: 1,
  code: 'draft',
  label: 'Brouillon',
  icon: 'FileText',
  active: true,
  isToSend: false,
  position: 10,
  createdAt: '2026-08-01T09:00:00.000000Z',
  updatedAt: '2026-08-01T09:00:00.000000Z',
}

const SOURCES = ['order', 'order_service', 'package']

function serve(rows: unknown[] = [status]) {
  const queries: URLSearchParams[] = []

  server.use(
    http.get(`${API}/statuses/sources`, () => HttpResponse.json({ data: SOURCES, meta: [] })),
    http.get(`${API}/statuses`, ({ request }) => {
      queries.push(new URL(request.url).searchParams)
      return HttpResponse.json(paginated(rows))
    }),
  )

  return queries
}

/** Les permissions d'écriture sont plateforme : le compte les porte à ce titre. */
const platform = (codes: string[]) =>
  platformMembership({ permissions: codes.map((code, index) => ({ id: `p-${index}`, code })) })

describe('StatusListPage', () => {
  it('affiche les statuts avec leur entité et leur code', async () => {
    serve()
    renderWithProviders(<StatusListPage />, { membership: withPermissions(['statuses.view']) })

    expect(await screen.findByText('draft')).toBeInTheDocument()
    expect(screen.getByText('Brouillon')).toBeInTheDocument()
    // L'alias technique reste visible sous le libellé français.
    expect(screen.getByText('Commande')).toBeInTheDocument()
    expect(screen.getByText('order')).toBeInTheDocument()
  })

  it('filtre par entité côté serveur', async () => {
    const queries = serve()
    renderWithProviders(<StatusListPage />, { membership: withPermissions(['statuses.view']) })

    await screen.findByText('draft')
    await userEvent.click(screen.getByLabelText('Entité'))
    await userEvent.click(await screen.findByRole('option', { name: /Colis/ }))

    await waitFor(() => {
      expect(queries[queries.length - 1].get('source')).toBe('package')
    })
  })

  /**
   * Le référentiel décrit le cycle de vie du domaine : un administrateur
   * d'organisme le lit, il ne l'écrit pas.
   */
  it('n’offre aucune écriture à un administrateur d’organisation', async () => {
    serve()
    renderWithProviders(<StatusListPage />, {
      membership: withPermissions(['statuses.view']),
    })

    await screen.findByText('draft')

    expect(screen.queryByRole('button', { name: /Nouveau statut/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Modifier le statut' })).not.toBeInTheDocument()
  })

  it('offre la création à la plateforme', async () => {
    serve()
    renderWithProviders(<StatusListPage />, {
      membership: platform(['statuses.view', 'statuses.create']),
    })

    expect(await screen.findByRole('button', { name: /Nouveau statut/ })).toBeInTheDocument()
  })

  it('envoie un nouveau statut avec son entité et ses réglages', async () => {
    serve()
    let body: unknown = null

    server.use(
      http.post(`${API}/statuses`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: status, meta: [] }, { status: 201 })
      }),
    )

    renderWithProviders(<StatusListPage />, {
      membership: platform(['statuses.view', 'statuses.create']),
    })

    await userEvent.click(await screen.findByRole('button', { name: /Nouveau statut/ }))

    // « Entité » nomme aussi le filtre de la liste : la saisie se fait dans le
    // dialogue, pas au-dessus du tableau.
    const dialog = within(await screen.findByRole('dialog'))

    await userEvent.click(dialog.getByLabelText(/^Entité/))
    await userEvent.click(await screen.findByRole('option', { name: /Commande/ }))
    await userEvent.type(dialog.getByLabelText(/^N°/), '99')
    await userEvent.type(dialog.getByLabelText(/^Code/), 'archived')
    await userEvent.type(dialog.getByLabelText(/^Libellé/), 'Archivée')
    await userEvent.click(dialog.getByRole('checkbox', { name: 'Envoi au client' }))
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())

    expect(body).toMatchObject({
      source: 'order',
      status: 99,
      code: 'archived',
      label: 'Archivée',
      isToSend: true,
      active: true,
    })
  })

  /** Déplacer un statut d'un domaine à l'autre emmènerait les enregistrements. */
  it('fige l’entité à la modification', async () => {
    serve()
    renderWithProviders(<StatusListPage />, {
      membership: platform(['statuses.view', 'statuses.update']),
    })

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier le statut' }))

    const dialog = within(await screen.findByRole('dialog'))

    expect(dialog.getByText(/L’entité ne change pas/)).toBeInTheDocument()
    expect(dialog.getByLabelText(/^Entité/)).toBeDisabled()
  })

  /** Le refus du serveur est rédigé pour être lu : il est affiché tel quel. */
  it('affiche le refus de suppression d’un statut encore utilisé', async () => {
    serve()

    server.use(
      http.delete(`${API}/statuses/${status.id}`, () =>
        HttpResponse.json(
          {
            message: 'Les données fournies sont invalides.',
            errors: { code: ['Ce statut est encore porté par 3 enregistrement(s).'] },
          },
          { status: 422 },
        ),
      ),
    )

    renderWithProviders(<StatusListPage />, {
      membership: platform(['statuses.view', 'statuses.delete']),
    })

    await userEvent.click(await screen.findByRole('button', { name: 'Supprimer' }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('button', { name: 'Supprimer' }))

    // Le motif du refus est affiché ; sans lui, le clic paraîtrait sans effet.
    expect(
      await screen.findByText('Ce statut est encore porté par 3 enregistrement(s).'),
    ).toBeInTheDocument()
    expect(screen.getByText('draft')).toBeInTheDocument()
  })
})
