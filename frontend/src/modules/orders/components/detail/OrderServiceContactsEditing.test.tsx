import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '../../pages/OrderDetailPage'
import { makeOrderDetail, ORDER_ID, PACKAGE_TREE } from '../../pages/orderDetailFixtures'

const SERVICE_ID = '01JQZ0000000000000SRVC01'
const CONTACT_ID = '01JQZ0000000000000CTCT01'

interface Calls {
  service: unknown
  contact: unknown
  removed: string | null
}

function renderDetail(calls: Calls) {
  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({ data: makeOrderDetail(), meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.patch(`${API}/orders/${ORDER_ID}/services/${SERVICE_ID}`, async ({ request }) => {
      calls.service = await request.json()
      return HttpResponse.json({ data: { id: SERVICE_ID }, meta: [] })
    }),
    http.patch(
      `${API}/orders/${ORDER_ID}/services/${SERVICE_ID}/contacts/${CONTACT_ID}`,
      async ({ request }) => {
        calls.contact = await request.json()
        return HttpResponse.json({ data: { id: CONTACT_ID }, meta: [] })
      },
    ),
    http.delete(`${API}/orders/${ORDER_ID}/services/${SERVICE_ID}/contacts/:id`, ({ params }) => {
      calls.removed = String(params.id)
      return new HttpResponse(null, { status: 204 })
    }),
    // Référentiels et listes du dialogue : vides, ils ne sont pas l'objet ici.
    http.get(`${API}/*`, () => HttpResponse.json(paginated([]))),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(['orders.view', 'order_services.update']),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

async function openServiceDialog() {
  await userEvent.click(await screen.findByRole('tab', { name: /^Services/ }))
  await userEvent.click((await screen.findAllByRole('button', { name: 'Modifier le service' }))[0])

  return within(await screen.findByRole('dialog'))
}

describe('contacts d’un service, depuis la fiche commande', () => {
  it('modifie un contact existant par sa propre route, après le service', async () => {
    const calls: Calls = { service: null, contact: null, removed: null }
    renderDetail(calls)

    const dialog = await openServiceDialog()
    const lastName = dialog.getByLabelText('Nom')
    await userEvent.clear(lastName)
    await userEvent.type(lastName, 'Rochat')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(calls.contact).not.toBeNull())
    expect(calls.service).not.toBeNull()
    expect(calls.contact).toMatchObject({ firstName: 'Sophie', lastName: 'Rochat' })
    expect(calls.removed).toBeNull()
  })

  it('retire un contact supprimé du formulaire', async () => {
    const calls: Calls = { service: null, contact: null, removed: null }
    renderDetail(calls)

    const dialog = await openServiceDialog()
    await userEvent.click(dialog.getByRole('button', { name: 'Retirer le contact' }))
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(calls.removed).toBe(CONTACT_ID))
    expect(calls.contact).toBeNull()
  })
})
