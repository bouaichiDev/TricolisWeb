import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

const ROW = { id: '01COM1', channel: 'email', channelLabel: 'E-mail', subject: 'Tournée de demain', status: 'sent', statusLabel: 'Envoyée', sentAt: '2026-11-02T16:00:00+00:00', deliveredAt: null }
const READ = [...ADMIN_PERMISSIONS, 'provider.communications.view']

describe('communications', () => {
  it('liste les communications reçues', async () => {
    server.use(http.get(`${API}/communications`, () => HttpResponse.json({ data: [ROW], meta: { currentPage: 1, perPage: 25, total: 1, lastPage: 1 } })))
    renderApp('/communications', makeContext(READ))

    expect(await screen.findByRole('link', { name: 'Tournée de demain' })).toBeInTheDocument()
  })

  it('affiche le message en texte, sans interpréter de balise', async () => {
    server.use(
      http.get(`${API}/communications/01COM1`, () =>
        HttpResponse.json({ data: { ...ROW, body: 'Quai 3 <img src=x onerror=alert(1)>', attachments: [{ fileName: 'plan.pdf', mimeType: 'application/pdf' }] }, meta: {} }),
      ),
    )
    const { container } = renderApp('/communications/01COM1', makeContext(READ))

    expect(await screen.findByText('Quai 3 <img src=x onerror=alert(1)>')).toBeInTheDocument()
    expect(container.querySelector('img')).toBeNull()
    expect(screen.getByText('plan.pdf')).toBeInTheDocument()
  })
})
