import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { AccessRequestListPage } from './AccessRequestListPage'

const PENDING = {
  id: 'req-1',
  companyName: 'Transports Atlas',
  contactName: 'Sara Bennani',
  email: 'sara@atlas.example',
  phone: '+212600000000',
  message: null,
  status: 'pending',
  decisionNote: null,
  decidedAt: null,
  organizationId: null,
  createdAt: '2026-09-05T09:00:00Z',
}

function render() {
  const calls: { url: string; body: unknown }[] = []

  server.use(
    http.get(`${API}/access-requests`, ({ request }) => {
      const status = new URL(request.url).searchParams.get('status')

      return HttpResponse.json({
        data: status === 'pending' ? [PENDING] : [],
        meta: { currentPage: 1, perPage: 25, total: status === 'pending' ? 1 : 0, lastPage: 1 },
        links: { first: null, last: null, prev: null, next: null },
      })
    }),
    http.post(`${API}/access-requests/:id/:decision`, async ({ request, params }) => {
      calls.push({ url: `${params.id as string}/${params.decision as string}`, body: await request.json() })

      return HttpResponse.json({ data: { ...PENDING, status: 'approved' }, meta: [] })
    }),
  )

  renderWithProviders(<AccessRequestListPage />, { route: '/access-requests' })

  return calls
}

/**
 * Accepter n'est pas un geste anodin : cela crée une organisation, un compte
 * administrateur, et envoie un courriel. La confirmation est donc obligatoire,
 * et le motif qu'elle propose est ce qui rend un refus relisible plus tard.
 */
describe('demandes d’accès, côté plateforme', () => {
  it('s’ouvre sur les demandes en attente', async () => {
    render()

    expect(await screen.findByText('Transports Atlas')).toBeInTheDocument()
  })

  it('n’accepte qu’après confirmation, et transmet le motif', async () => {
    const calls = render()

    await userEvent.click(await screen.findByRole('button', { name: 'Accepter' }))

    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText(/sera créée avec son compte administrateur/)).toBeInTheDocument()
    expect(calls).toHaveLength(0)

    await userEvent.type(within(dialog).getByLabelText(/Motif/), 'Client connu.')
    await userEvent.click(within(dialog).getByRole('button', { name: /Accepter et créer/ }))

    await waitFor(() => expect(calls).toHaveLength(1))
    expect(calls[0]).toEqual({ url: 'req-1/approve', body: { note: 'Client connu.' } })
  })

  it('refuse sans rien créer', async () => {
    const calls = render()

    await userEvent.click(await screen.findByRole('button', { name: 'Refuser' }))

    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText(/Rien ne sera créé/)).toBeInTheDocument()

    await userEvent.click(within(dialog).getByRole('button', { name: 'Refuser' }))

    await waitFor(() => expect(calls).toHaveLength(1))
    expect(calls[0].url).toBe('req-1/reject')
  })
})
