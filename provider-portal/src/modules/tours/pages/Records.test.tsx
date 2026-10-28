import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { makeTour } from '@/test/tourFixtures'

vi.mock('@/shared/components/map/LazyTrackingMap', () => ({
  LazyTrackingMap: ({ position }: { position: { latitude: number } | null }) => <div data-testid="tracking-map">{position?.latitude}</div>,
}))

const READ = [...ADMIN_PERMISSIONS, 'provider.tours.view', 'provider.tracking.view', 'provider.documents.view', 'provider.pod.view']
const arrived = () => {
  const tour = makeTour({ status: 'in_progress', statusLabel: 'En cours' })
  tour.stops[0] = { ...tour.stops[0], status: 'arrived', actions: { canArrive: false, canComplete: true, canSkip: true, canRecordProof: true } } as (typeof tour.stops)[0]

  return tour
}

describe('chronologie, documents et preuves', () => {
  it('montre la chronologie et la dernière position enregistrée', async () => {
    server.use(
      http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: makeTour(), meta: {} })),
      http.get(`${API}/tours/01TOUR1/tracking`, () =>
        HttpResponse.json({
          data: [
            { type: 'tour_started', label: 'Tournée démarrée', occurredAt: '2026-11-03T07:05:00+00:00', stopSequence: null, serviceNumber: null, latitude: null, longitude: null },
            { type: 'event', label: 'Tentative de livraison', occurredAt: '2026-11-03T08:15:00+00:00', stopSequence: 1, serviceNumber: 'SRV-00042', latitude: 46.2, longitude: 6.14 },
          ],
          meta: {},
        }),
      ),
    )
    renderApp('/tours/01TOUR1', makeContext(READ))

    await userEvent.setup().click(await screen.findByRole('tab', { name: 'Chronologie' }))

    const list = await screen.findByRole('list', { name: 'Chronologie' })
    expect(within(list).getByText('Tentative de livraison')).toBeInTheDocument()
    expect(screen.getByTestId('tracking-map')).toHaveTextContent('46.2')
  })

  it('cache les onglets que la permission ne couvre pas', async () => {
    server.use(http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: makeTour(), meta: {} })))
    renderApp('/tours/01TOUR1', makeContext([...ADMIN_PERMISSIONS, 'provider.tours.view']))

    expect(await screen.findByRole('tab', { name: 'Arrêts' })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Chronologie' })).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Documents' })).not.toBeInTheDocument()
  })

  it('télécharge un document avec le jeton, sans lien direct', async () => {
    let downloaded = false
    server.use(
      http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: makeTour(), meta: {} })),
      http.get(`${API}/tours/01TOUR1/documents`, () =>
        HttpResponse.json({ data: [{ id: '01DOC1', fileName: 'consignes.pdf', documentType: 'instructions', mimeType: 'application/pdf', size: 2048, createdAt: null }], meta: {} }),
      ),
      http.get(`${API}/documents/01DOC1`, () => {
        downloaded = true
        return new HttpResponse('pdf', { headers: { 'Content-Type': 'application/pdf' } })
      }),
    )
    URL.createObjectURL = vi.fn(() => 'blob:doc')
    URL.revokeObjectURL = vi.fn()
    renderApp('/tours/01TOUR1', makeContext(READ))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('tab', { name: 'Documents' }))
    await user.click(await screen.findByRole('button', { name: 'Télécharger' }))

    await waitFor(() => expect(downloaded).toBe(true))
    expect(screen.queryByRole('link', { name: /consignes/ })).not.toBeInTheDocument()
  })

  it('enregistre une preuve en multipart, sans heure', async () => {
    let fields: Record<string, string> = {}
    server.use(
      http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: arrived(), meta: {} })),
      http.get(`${API}/tours/01TOUR1/stops/01STOP1/proofs`, () => HttpResponse.json({ data: [], meta: {} })),
      http.post(`${API}/tours/01TOUR1/stops/01STOP1/proofs`, async ({ request }) => {
        const form = await request.formData()
        fields = Object.fromEntries([...form.entries()].map(([key, value]) => [key, typeof value === 'string' ? value : 'fichier']))
        return HttpResponse.json({ data: { id: '01POD1' }, meta: {} }, { status: 201 })
      }),
    )
    renderApp('/tours/01TOUR1', makeContext([...READ, 'provider.pod.create']))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Preuve de livraison' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Réceptionnaire'), 'Marie Réception')
    await user.upload(within(dialog).getByLabelText('Signature (image)'), new File(['png'], 'signature.png', { type: 'image/png' }))
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer la preuve' }))

    await waitFor(() => expect(fields).toEqual({ orderServiceId: '01OSVC1', recipientName: 'Marie Réception', signature: 'fichier' }))
  })
})
