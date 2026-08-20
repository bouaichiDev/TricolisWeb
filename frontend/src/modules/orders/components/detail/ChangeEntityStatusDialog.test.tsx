import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '../../pages/OrderDetailPage'
import {
  LINE_ID,
  makeOrderDetail,
  ORDER_ID,
  PACKAGE_ID,
  PACKAGE_TREE,
} from '../../pages/orderDetailFixtures'

/**
 * Changer le statut d'une ligne ou d'un colis.
 *
 * Ni l'une ni l'autre n'a de route dédiée : leur `status` est une chaîne libre
 * que la modification ordinaire accepte. La liste proposée vient donc du
 * référentiel — seul endroit où ce vocabulaire est décrit.
 */
const status = (code: string, label: string, position: number) => ({
  id: `01JQZ0000000000000STA${position}`,
  source: 'order_line',
  status: position,
  code,
  label,
  icon: null,
  active: true,
  isToSend: false,
  allowsContentChanges: true,
  requiresReason: false,
  position: position * 10,
  createdAt: '2026-08-01T09:00:00.000000Z',
  updatedAt: '2026-08-01T09:00:00.000000Z',
})

function renderDetail(permissions: string[], statuses = [status('active', 'Active', 1), status('closed', 'Clôturée', 2)]) {
  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({ data: makeOrderDetail(), meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/statuses`, () => HttpResponse.json(paginated(statuses))),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

async function openStatusDialog(tab: string) {
  await userEvent.click(await screen.findByRole('tab', { name: new RegExp(`^${tab}`) }))
  await userEvent.click((await screen.findAllByRole('button', { name: 'Actions' }))[0])
  await userEvent.click(await screen.findByRole('menuitem', { name: 'Changer le statut' }))
}

describe('statut d’une ligne', () => {
  it('propose les statuts du référentiel et envoie le choix', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'order_lines.update'])

    server.use(
      http.patch(`${API}/orders/${ORDER_ID}/lines/${LINE_ID}`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: {}, meta: [] })
      }),
    )

    await openStatusDialog('Lignes')
    await userEvent.click(await screen.findByRole('combobox'))

    const listbox = await screen.findByRole('listbox')
    // Le statut courant est montré, mais pas sélectionnable.
    expect(within(listbox).getByRole('option', { name: /^Active/ })).toHaveAttribute(
      'aria-disabled',
      'true',
    )

    await userEvent.click(within(listbox).getByRole('option', { name: /^Clôturée/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Appliquer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ status: 'closed' })
  })

  /** Rien n'est décrit pour cette entité : l'écran le dit et renvoie au bon endroit. */
  it('renvoie au référentiel quand aucun statut n’est décrit', async () => {
    renderDetail(['orders.view', 'order_lines.update'], [])

    await openStatusDialog('Lignes')

    expect(await screen.findByText(/Aucun statut n’est décrit/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Statuts' })).toBeInTheDocument()
  })
})

describe('statut d’un colis', () => {
  it('envoie le choix par la modification du colis', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'packages.update'])

    server.use(
      http.patch(`${API}/orders/${ORDER_ID}/packages/${PACKAGE_ID}`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: {}, meta: [] })
      }),
    )

    await openStatusDialog('Colis')
    await userEvent.click(await screen.findByRole('combobox'))
    await userEvent.click(await screen.findByRole('option', { name: /^Clôturée/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Appliquer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ status: 'closed' })
  })

  /** Sans la permission, l'action ne figure pas au menu. */
  it('masque l’action sans la permission', async () => {
    renderDetail(['orders.view'])

    await userEvent.click(await screen.findByRole('tab', { name: /^Colis/ }))
    await userEvent.click((await screen.findAllByRole('button', { name: 'Actions' }))[0])

    expect(
      screen.queryByRole('menuitem', { name: 'Changer le statut' }),
    ).not.toBeInTheDocument()
  })
})

describe('statut d’un service', () => {
  /** Le service a sa propre route : l'action est sur la vignette, pas dans un menu. */
  it('offre le changement depuis la vignette, sans ouvrir le détail', async () => {
    renderDetail(['orders.view', 'order_services.change_status'])

    await userEvent.click(await screen.findByRole('tab', { name: /^Services/ }))

    expect(
      await screen.findByRole('button', { name: 'Changer le statut du service' }),
    ).toBeInTheDocument()
  })
})
