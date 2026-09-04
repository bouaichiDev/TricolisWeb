import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, platformMembership } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StatusTransitionsDialog } from './StatusTransitionsDialog'
import type { Status } from '../types/status'

function makeStatus(overrides: Partial<Status>): Status {
  return {
    id: 'x',
    source: 'order',
    status: 1,
    code: 'draft',
    label: 'Brouillon',
    icon: null,
    active: true,
    isToSend: false,
    allowsContentChanges: true,
    requiresReason: false,
    position: 10,
    createdAt: '2026-08-01T09:00:00.000000Z',
    updatedAt: '2026-08-01T09:00:00.000000Z',
    ...overrides,
  }
}

const DRAFT = makeStatus({ id: 'st-draft', code: 'draft', label: 'Brouillon', position: 10 })
const CONFIRMED = makeStatus({ id: 'st-conf', code: 'confirmed', label: 'Confirmée', position: 20 })
const READY = makeStatus({ id: 'st-ready', code: 'ready', label: 'Prête', position: 30 })

function serve(existing: { toStatusId: string; isManual: boolean }[]) {
  const sent: unknown[] = []

  server.use(
    http.get(`${API}/statuses`, () =>
      HttpResponse.json(paginated([DRAFT, CONFIRMED, READY])),
    ),
    http.get(`${API}/statuses/${DRAFT.id}/transitions`, () =>
      HttpResponse.json({
        data: existing.map((item, index) => ({
          id: `tr-${index}`,
          fromStatusId: DRAFT.id,
          ...item,
        })),
        meta: [],
      }),
    ),
    http.put(`${API}/statuses/${DRAFT.id}/transitions`, async ({ request }) => {
      sent.push(await request.json())
      return HttpResponse.json({ data: [], meta: [] })
    }),
  )

  return sent
}

const render = () =>
  renderWithProviders(
    <StatusTransitionsDialog status={DRAFT} open onOpenChange={() => {}} />,
    { membership: platformMembership() },
  )

describe('StatusTransitionsDialog', () => {
  it('propose les autres statuts de la même entité, jamais lui-même', async () => {
    serve([])
    render()

    expect(await screen.findByRole('checkbox', { name: /Confirmée/ })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: /Prête/ })).toBeInTheDocument()
    expect(screen.queryByRole('checkbox', { name: /Brouillon/ })).not.toBeInTheDocument()
  })

  it('coche les transitions déjà enregistrées', async () => {
    serve([{ toStatusId: CONFIRMED.id, isManual: true }])
    render()

    expect(await screen.findByRole('checkbox', { name: /Confirmée/ })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: /Prête/ })).not.toBeChecked()
  })

  /** L'ensemble part d'un bloc : c'est ce qui évite un graphe transitoire. */
  it('envoie le jeu complet, ajouts et retraits compris', async () => {
    const sent = serve([{ toStatusId: CONFIRMED.id, isManual: true }])
    render()

    await userEvent.click(await screen.findByRole('checkbox', { name: /Confirmée/ }))
    await userEvent.click(screen.getByRole('checkbox', { name: /Prête/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(sent).toHaveLength(1))

    expect(sent[0]).toEqual({
      transitions: [{ toStatusId: READY.id, isManual: true }],
    })
  })

  it('vide le cycle de vie quand tout est décoché', async () => {
    const sent = serve([{ toStatusId: CONFIRMED.id, isManual: true }])
    render()

    await userEvent.click(await screen.findByRole('checkbox', { name: /Confirmée/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toEqual({ transitions: [] })
  })

  /**
   * Une transition non manuelle reste valide — la planification l'emprunte —
   * mais n'apparaît pas dans le sélecteur de statut d'une commande.
   */
  it('permet de retirer une transition du choix manuel', async () => {
    const sent = serve([{ toStatusId: CONFIRMED.id, isManual: true }])
    render()

    await screen.findByRole('checkbox', { name: /Confirmée/ })
    await userEvent.click(screen.getByRole('checkbox', { name: /Posable à la main/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(sent).toHaveLength(1))

    expect(sent[0]).toEqual({
      transitions: [{ toStatusId: CONFIRMED.id, isManual: false }],
    })
  })

  it('signale une destination inactive sans l’interdire', async () => {
    server.use(
      http.get(`${API}/statuses`, () =>
        HttpResponse.json(paginated([DRAFT, { ...CONFIRMED, active: false }])),
      ),
      http.get(`${API}/statuses/${DRAFT.id}/transitions`, () =>
        HttpResponse.json({ data: [], meta: [] }),
      ),
    )

    render()

    const item = (await screen.findByRole('checkbox', { name: /Confirmée/ })).closest('li')

    expect(within(item as HTMLElement).getByText(/Statut inactif/)).toBeInTheDocument()
  })
})
