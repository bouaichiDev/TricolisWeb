import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '../../pages/OrderDetailPage'
import {
  CHILD_PACKAGE_ID,
  makeOrderDetail,
  ORDER_ID,
  PACKAGE_TREE,
} from '../../pages/orderDetailFixtures'

const SERVICE_ID = '01JQZ0000000000000SRVC01'
const LINK_ID = '01JQZ0000000000000SVPK01'

/**
 * Par quels services un colis passe.
 *
 * C'est `OrderServicePackage` lue à l'envers : la fiche d'un service dit quels
 * colis il transporte, celle d'un colis dit par quels services il passe. Un
 * même colis est souvent chargé par l'un et livré par l'autre.
 */
function renderDetail(permissions: string[], detail = makeOrderDetail()) {
  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () => HttpResponse.json({ data: detail, meta: [] })),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/services/:serviceId/packages`, () =>
      HttpResponse.json({ data: [], meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

/** Ouvre l'onglet Colis puis la fiche de l'un des colis. */
async function openPackage(index = 0) {
  await userEvent.click(await screen.findByRole('tab', { name: /^Colis/ }))
  await screen.findByText('PAL-1')
  await userEvent.click(screen.getAllByRole('button', { name: 'Contenu du colis' })[index])

  return within(await screen.findByRole('dialog'))
}

describe('services d’un colis', () => {
  it('liste les services qui prennent le colis en charge', async () => {
    renderDetail(['orders.view'])

    const sheet = await openPackage()

    expect(sheet.getByText('Services qui prennent ce colis')).toBeInTheDocument()
    expect(sheet.getByText('Livraison standard')).toBeInTheDocument()
  })

  /** Le second colis n'est pris par aucun service : la liste le dit. */
  it('dit quand aucun service ne prend le colis', async () => {
    renderDetail(['orders.view'])

    const sheet = await openPackage(1)

    expect(sheet.getByText(/Aucun service ne prend ce colis/)).toBeInTheDocument()
  })

  it('lie le colis à un service, par la route du service', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'order_services.update'])

    server.use(
      http.post(`${API}/orders/${ORDER_ID}/services/${SERVICE_ID}/packages`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: {}, meta: [] }, { status: 201 })
      }),
    )

    // Le second colis n'est lie a rien : le service est donc proposable.
    const sheet = await openPackage(1)

    await userEvent.click(sheet.getByLabelText('Ajouter un service'))
    await userEvent.click(await screen.findByRole('option', { name: /Livraison standard/ }))
    await userEvent.click(sheet.getByRole('button', { name: 'Lier' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({ packageId: CHILD_PACKAGE_ID })
  })

  it('retire la liaison par le service qui la porte', async () => {
    let deleted: string | null = null
    renderDetail(['orders.view', 'order_services.update'])

    server.use(
      http.delete(
        `${API}/orders/${ORDER_ID}/services/${SERVICE_ID}/packages/${LINK_ID}`,
        () => {
          deleted = LINK_ID
          return new HttpResponse(null, { status: 204 })
        },
      ),
    )

    const sheet = await openPackage()
    await userEvent.click(sheet.getByRole('button', { name: 'Retirer ce colis du service' }))

    await waitFor(() => expect(deleted).toBe(LINK_ID))
  })

  /** Sans la permission, la liste se lit mais ne se modifie pas. */
  it('masque l’ajout et le retrait sans order_services.update', async () => {
    renderDetail(['orders.view'])

    const sheet = await openPackage()

    expect(sheet.getByText('Livraison standard')).toBeInTheDocument()
    expect(sheet.queryByLabelText('Ajouter un service')).not.toBeInTheDocument()
    expect(
      sheet.queryByRole('button', { name: 'Retirer ce colis du service' }),
    ).not.toBeInTheDocument()
  })
})
