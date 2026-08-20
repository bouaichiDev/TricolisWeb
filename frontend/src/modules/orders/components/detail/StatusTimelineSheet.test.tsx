import { screen, waitFor } from '@testing-library/react'
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
  PACKAGE_TREE,
} from '../../pages/orderDetailFixtures'
import type { OrderDetail } from '../../types/orderDetail'

const AUDIT = {
  id: '01JQZ0000000000000AUDT01',
  organizationId: '01JQZ0000000000000000ORG1',
  userId: null,
  action: 'updated',
  entityType: 'order_line',
  entityId: LINE_ID,
  oldValues: { quantity: 10 },
  newValues: { quantity: 12 },
  ipAddress: null,
  createdAt: '2026-08-02T09:00:00.000000Z',
}

function renderDetail(permissions: string[], order: Partial<OrderDetail> = {}) {
  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({ data: makeOrderDetail(order), meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/services/:serviceId/packages`, () =>
      HttpResponse.json({ data: [], meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([AUDIT]))),
    http.get(`${API}/package-types`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/package-grouping-types`, () => HttpResponse.json(paginated([]))),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

/** Le nom accessible d'un onglet porte son compteur : « Lignes 1 ». */
const openTab = async (name: string) =>
  userEvent.click(await screen.findByRole('tab', { name: new RegExp(`^${name}`) }))

describe('historique par élément', () => {
  /**
   * Il n'existe pas de table d'historique : chaque écriture est journalisée
   * dans l'audit avec son type d'entité et son identifiant.
   */
  it('charge l’historique d’une ligne seulement une fois ouvert', async () => {
    const queries: URLSearchParams[] = []
    renderDetail(['orders.view', 'audit.view'])

    server.use(
      http.get(`${API}/audit-logs`, ({ request }) => {
        queries.push(new URL(request.url).searchParams)
        return HttpResponse.json(paginated([AUDIT]))
      }),
    )

    await openTab('Lignes')
    await screen.findByText('Carton renforcé')

    expect(queries).toHaveLength(0)

    // L'historique s'ouvre en tiroir depuis l'action de la ligne.
    await userEvent.click((await screen.findAllByRole('button', { name: /Voir l’historique/ }))[0])

    await waitFor(() => expect(queries.length).toBeGreaterThan(0))
    expect(queries[0].get('entityType')).toBe('order_line')
    expect(queries[0].get('entityId')).toBe(LINE_ID)

    // Deux sections : le parcours de statuts, puis les modifications.
    expect(await screen.findByText('Historique des statuts')).toBeInTheDocument()
    expect(screen.getByText('Modifications')).toBeInTheDocument()
    expect(screen.getByText('10 → 12')).toBeInTheDocument()
  })

  /**
   * Le parcours croise deux sources : les statuts atteints viennent de l'audit,
   * ceux à venir du référentiel.
   */
  it('montre les statuts atteints puis ceux à venir', async () => {
    renderDetail(['orders.view', 'audit.view'])

    server.use(
      http.get(`${API}/audit-logs`, () =>
        HttpResponse.json(
          paginated([
            { ...AUDIT, action: 'created', oldValues: null, newValues: { status: 'active' } },
          ]),
        ),
      ),
      http.get(`${API}/statuses`, () =>
        HttpResponse.json(
          paginated([
            {
              id: '01JQZ00000000000000STA1',
              source: 'order_line',
              status: 1,
              code: 'active',
              label: 'Active',
              icon: null,
              active: true,
              isToSend: false,
              allowsContentChanges: true,
              requiresReason: false,
              position: 10,
              createdAt: '2026-08-01T09:00:00.000000Z',
              updatedAt: '2026-08-01T09:00:00.000000Z',
            },
          ]),
        ),
      ),
      http.get(`${API}/statuses/01JQZ00000000000000STA1/transitions`, () =>
        HttpResponse.json({
          data: [
            {
              id: '01JQZ00000000000000TRA1',
              fromStatusId: '01JQZ00000000000000STA1',
              toStatusId: '01JQZ00000000000000STA2',
              isManual: true,
              to: {
                id: '01JQZ00000000000000STA2',
                source: 'order_line',
                status: 2,
                code: 'delivered',
                label: 'Livrée',
                icon: null,
                active: true,
                isToSend: false,
                allowsContentChanges: false,
                requiresReason: false,
                position: 20,
                createdAt: '2026-08-01T09:00:00.000000Z',
                updatedAt: '2026-08-01T09:00:00.000000Z',
              },
            },
          ],
          meta: [],
        }),
      ),
    )

    await openTab('Lignes')
    await screen.findByText('Carton renforcé')
    await userEvent.click((await screen.findAllByRole('button', { name: /Voir l’historique/ }))[0])

    // Atteint : daté. À venir : sans date, et annoncé comme tel.
    expect(await screen.findByText('Élément créé')).toBeInTheDocument()
    expect(screen.getByText('Livrée')).toBeInTheDocument()
    expect(screen.getByText('Pas encore atteint')).toBeInTheDocument()
  })

  it('explique le manque de permission plutôt que d’afficher un bloc vide', async () => {
    renderDetail(['orders.view'])

    await openTab('Lignes')
    await screen.findByText('Carton renforcé')
    await userEvent.click((await screen.findAllByRole('button', { name: /Voir l’historique/ }))[0])

    expect(await screen.findByText(/demande la permission/i)).toBeInTheDocument()
  })
})
