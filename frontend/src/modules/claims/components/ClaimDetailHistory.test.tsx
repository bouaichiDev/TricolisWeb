import { screen, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import type { Claim } from '../types/claim'
import { ClaimDetailDrawer } from './ClaimDetailDrawer'

const CLAIM_ID = '01JQZ0000000000000CLAIM1'
const CUSTOMER_ID = '01M1H7SQH14KEB3YVEKTCT6MKQ'
const ORDER_ID = '01JQZ00000000000000ORD01'

const claim: Claim = {
  id: CLAIM_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  customerId: CUSTOMER_ID,
  orderId: ORDER_ID,
  tourId: null,
  title: 'colis manquant',
  claimType: 'MQ',
  result: null,
  cost: null,
  status: 'created',
  responsibleUserId: null,
  createdAt: '2026-09-12T01:25:00+00:00',
  closedAt: null,
  customer: { id: CUSTOMER_ID, code: 'CH-01', name: 'Client Genève' },
  order: { id: ORDER_ID, orderNumber: 'CH-01-2026-000175' },
}

const log = (action: string, oldValues: object | null, newValues: object) => ({
  id: `01JQZ0000000000000AUD${action.length}`,
  organizationId: claim.organizationId,
  userId: null,
  action,
  entityType: 'claim',
  entityId: CLAIM_ID,
  oldValues,
  newValues,
  ipAddress: null,
  createdAt: '2026-09-12T01:25:00+00:00',
})

function render() {
  server.use(
    http.get(`${API}/claims/${CLAIM_ID}`, () => HttpResponse.json({ data: claim, meta: [] })),
    http.get(`${API}/audit-logs`, () =>
      HttpResponse.json(
        paginated([
          log('claim.created', null, {
            claim_type: 'MQ',
            customer_id: CUSTOMER_ID,
            status: 'created',
            title: 'colis manquant',
          }),
          log('claim.updated', { order_id: '01JQZ00000000000000ORD99' }, { order_id: ORDER_ID }),
        ]),
      ),
    ),
    // Statuts, pièces jointes : vides, ils ne sont pas l'objet ici.
    http.get(`${API}/*`, () => HttpResponse.json(paginated([]))),
  )

  renderWithProviders(<ClaimDetailDrawer claim={claim} onClose={() => undefined} />, {
    membership: withPermissions(['claims.view']),
  })
}

/**
 * Le journal parle en colonnes et en identifiants ; la fiche, elle, parle au
 * gestionnaire. `customer_id → 01M1H7…` ne lui dit rien.
 */
describe('historique d’une réclamation', () => {
  it('nomme les champs et les éléments au lieu de montrer des identifiants', async () => {
    render()

    const drawer = within(await screen.findByRole('dialog'))

    expect(await drawer.findByText('Client')).toBeInTheDocument()
    expect(drawer.getByText('— → Client Genève')).toBeInTheDocument()
    expect(drawer.getByText('— → Créé')).toBeInTheDocument()
    expect(drawer.getByText('Type')).toBeInTheDocument()
    expect(drawer.getByText('Création')).toBeInTheDocument()
    expect(drawer.queryByText(/customer_id|01M1H7/)).not.toBeInTheDocument()
  })

  it('dit indisponible un élément que la fiche ne connaît plus', async () => {
    render()

    const drawer = within(await screen.findByRole('dialog'))

    expect(
      await drawer.findByText('élément non disponible → CH-01-2026-000175'),
    ).toBeInTheDocument()
  })
})
