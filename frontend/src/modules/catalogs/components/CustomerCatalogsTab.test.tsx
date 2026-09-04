import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { CustomerCatalogsTab } from './CustomerCatalogsTab'

const CUSTOMER_ID = '01JQZ000000000000000CUST'

const catalog = {
  id: '01JQZ0000000000000CATA01',
  customerId: CUSTOMER_ID,
  code: 'CAT-2026',
  name: 'Catalogue général',
  description: null,
  status: 'active',
  itemCount: 12,
  createdAt: '2026-02-01T10:00:00.000000Z',
  updatedAt: '2026-02-01T10:00:00.000000Z',
}

/** Les catalogues sont portés par le client : la route l'est aussi. */
function serveCatalogs() {
  const calls: string[] = []

  server.use(
    http.get(`${API}/customers/${CUSTOMER_ID}/catalogs`, ({ request }) => {
      calls.push(request.url)
      return HttpResponse.json(paginated([catalog]))
    }),
  )

  return calls
}

describe('CustomerCatalogsTab', () => {
  it('liste les catalogues du client', async () => {
    const calls = serveCatalogs()
    renderWithProviders(
      <CustomerCatalogsTab customerId={CUSTOMER_ID} catalogEnabled />,
      { membership: withPermissions(['catalogs.view']) },
    )

    expect(await screen.findByText('CAT-2026')).toBeInTheDocument()
    expect(screen.getByText('Catalogue général')).toBeInTheDocument()
    expect(calls[0]).toContain(`/customers/${CUSTOMER_ID}/catalogs`)
  })

  /**
   * Le catalogue est une capacité facultative du client. Quand elle est
   * désactivée, l'onglet l'explique — et n'interroge pas l'API, dont la réponse
   * serait de toute façon vide.
   */
  it('explique la capacité désactivée sans interroger l’API', async () => {
    const calls = serveCatalogs()
    renderWithProviders(
      <CustomerCatalogsTab customerId={CUSTOMER_ID} catalogEnabled={false} />,
      { membership: withPermissions(['catalogs.view']) },
    )

    expect(await screen.findByText(/Catalogue désactivé/i)).toBeInTheDocument()
    expect(screen.queryByText('CAT-2026')).not.toBeInTheDocument()
    expect(calls).toHaveLength(0)
  })

  it('n’offre la création qu’avec la permission correspondante', async () => {
    serveCatalogs()
    const { unmount } = renderWithProviders(
      <CustomerCatalogsTab customerId={CUSTOMER_ID} catalogEnabled />,
      { membership: withPermissions(['catalogs.view']) },
    )

    await screen.findByText('CAT-2026')
    expect(screen.queryByRole('link', { name: /Nouveau catalogue/i })).not.toBeInTheDocument()

    unmount()
    serveCatalogs()
    renderWithProviders(<CustomerCatalogsTab customerId={CUSTOMER_ID} catalogEnabled />, {
      membership: withPermissions(['catalogs.view', 'catalogs.create']),
    })

    expect(await screen.findByRole('link', { name: /Nouveau catalogue/i })).toBeInTheDocument()
  })
})
