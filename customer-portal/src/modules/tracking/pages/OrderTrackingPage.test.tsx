import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { makeService, makeTracking } from '@/test/trackingFixtures'

import type { OrderTracking } from '../types'

vi.mock('@/shared/components/map/LazyTrackingMap', () => ({
  LazyTrackingMap: ({ points, position }: { points: unknown[]; position: unknown }) => (
    <div data-testid="tracking-map">{`${points.length} point(s)${position ? ' + véhicule' : ''}`}</div>
  ),
}))

const PERMISSIONS = ['customer.orders.view', 'customer.tracking.view', 'customer.orders.track']

function serveTracking(tracking: OrderTracking) {
  server.use(http.get(`${API}/orders/01ORDER/tracking`, () => HttpResponse.json({ data: tracking, meta: {} })))
}

describe('order tracking page', () => {
  it('shows the header, progress, timeline and the unavailable ETA and position', async () => {
    serveTracking(makeTracking())

    renderApp('/orders/01ORDER/tracking', makeContext(PERMISSIONS))

    expect(await screen.findByRole('heading', { name: 'Suivi IKEA-2026-000001' })).toBeInTheDocument()
    expect(screen.getByRole('progressbar', { name: /Services effectués : 1 \/ 2/ })).toHaveAttribute('aria-valuenow', '50')
    expect(screen.getByText('ETA indisponible')).toBeInTheDocument()
    expect(screen.getByTestId('position-status')).toHaveTextContent('Position indisponible')
    const timeline = screen.getByRole('list', { name: 'Timeline de suivi' })
    expect(within(timeline).getByText('Livraison en route')).toBeInTheDocument()
    expect(within(timeline).getByText(/Prévu/)).toBeInTheDocument()
  })

  it('shows the ETA and the vehicle position when the backend gives them', async () => {
    serveTracking(
      makeTracking({
        eta: { available: true, estimatedAt: '2026-10-01T09:30:00', source: 'planning', orderServiceId: '01SVC1' },
        map: { available: true, currentPosition: { latitude: 46.52, longitude: 6.63, recordedAt: '2026-10-01T09:00:00+02:00' }, points: [{ orderServiceId: '01SVC1', label: 'Livraison', latitude: 46.5, longitude: 6.6, completed: false }] },
      }),
    )

    renderApp('/orders/01ORDER/tracking', makeContext(PERMISSIONS))

    expect(await screen.findByTestId('eta-value')).toHaveTextContent('09:30')
    expect(screen.getByTestId('tracking-map')).toHaveTextContent('1 point(s) + véhicule')
    expect(screen.getByTestId('position-status')).toHaveTextContent('Position du véhicule relevée')
  })

  it('narrows the view to the selected service', async () => {
    serveTracking(makeTracking())
    renderApp('/orders/01ORDER/tracking', makeContext(PERMISSIONS))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('tab', { name: /Montage/ }))

    expect(screen.getAllByTestId('service-tracking-card')).toHaveLength(1)
    const timeline = screen.getByRole('list', { name: 'Timeline de suivi' })
    expect(within(timeline).queryByText('Livraison en route')).not.toBeInTheDocument()
    expect(within(timeline).getByText('Montage : passage prévu')).toBeInTheDocument()
  })

  it('says when tracking is not available yet', async () => {
    serveTracking(makeTracking({ available: false, services: [makeService({ slot: null })], timeline: [] }))

    renderApp('/orders/01ORDER/tracking', makeContext(PERMISSIONS))

    expect(await screen.findByText('Tracking indisponible')).toBeInTheDocument()
    expect(screen.getByText('Aucun événement pour le moment.')).toBeInTheDocument()
  })

  it('presents an order of another customer as not found', async () => {
    server.use(http.get(`${API}/orders/01QOQA/tracking`, () => HttpResponse.json({ message: 'Introuvable.' }, { status: 404 })))

    renderApp('/orders/01QOQA/tracking', makeContext(PERMISSIONS))

    expect(await screen.findByText('Commande introuvable')).toBeInTheDocument()
  })

  it('is closed without the tracking permissions', async () => {
    renderApp('/orders/01ORDER/tracking', makeContext(['customer.orders.view']))

    expect(await screen.findByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
  })
})
