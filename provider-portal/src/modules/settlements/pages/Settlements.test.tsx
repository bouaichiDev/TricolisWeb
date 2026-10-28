import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

const SETTLEMENT = {
  id: '01STL1', settlementNumber: 'STL-ALPHA-1', periodFrom: '2026-10-01', periodTo: '2026-10-31', status: 'closed', statusLabel: 'Clôturé',
  subtotal: '240.00', taxTotal: '19.44', total: '259.44',
}
const page = (rows: unknown[]) => ({ data: rows, meta: { currentPage: 1, perPage: 25, total: rows.length, lastPage: 1 } })
const FINANCE = [...ADMIN_PERMISSIONS, 'provider.settlements.view']

describe('décomptes', () => {
  it('liste les décomptes avec le total du serveur', async () => {
    server.use(http.get(`${API}/settlements`, () => HttpResponse.json(page([SETTLEMENT]))))
    renderApp('/settlements', makeContext(FINANCE))

    expect(await screen.findByRole('link', { name: 'STL-ALPHA-1' })).toBeInTheDocument()
    expect(screen.getByText(/259[.,]44/)).toBeInTheDocument()
  })

  it('détaille les lignes et télécharge un document avec le jeton', async () => {
    let downloaded = false
    server.use(
      http.get(`${API}/settlements/01STL1`, () =>
        HttpResponse.json({ data: { ...SETTLEMENT, lines: [{ id: 'L1', serviceNumber: 'SRV-00042', description: 'Livraison', quantity: '3.000', unitCost: '80.00', totalCost: '240.00' }] }, meta: {} }),
      ),
      http.get(`${API}/settlements/01STL1/documents`, () =>
        HttpResponse.json({ data: [{ id: '01DOC1', fileName: 'releve.pdf', documentType: 'settlement', mimeType: 'application/pdf', size: 10, createdAt: null }], meta: {} }),
      ),
      http.get(`${API}/settlements/01STL1/documents/01DOC1`, () => {
        downloaded = true
        return new HttpResponse('pdf')
      }),
    )
    URL.createObjectURL = vi.fn(() => 'blob:x')
    URL.revokeObjectURL = vi.fn()
    renderApp('/settlements/01STL1', makeContext([...FINANCE, 'provider.settlements.documents.view']))

    expect(await screen.findByText(/SRV-00042/)).toBeInTheDocument()
    await userEvent.setup().click(await screen.findByRole('button', { name: 'Télécharger' }))

    await waitFor(() => expect(downloaded).toBe(true))
  })

  it('n’offre aucun bouton de modification', async () => {
    server.use(http.get(`${API}/settlements/01STL1`, () => HttpResponse.json({ data: { ...SETTLEMENT, lines: [] }, meta: {} })))
    renderApp('/settlements/01STL1', makeContext(FINANCE))

    expect(await screen.findByRole('heading', { name: 'Décompte STL-ALPHA-1' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /modifier|valider|recalculer|supprimer|télécharger/i })).not.toBeInTheDocument()
  })

  it('refuse la page sans la permission', async () => {
    renderApp('/settlements', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByText('Accès refusé')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'STL-ALPHA-1' })).not.toBeInTheDocument()
  })
})
