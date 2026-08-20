import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { CatalogItemTable } from './CatalogItemTable'

const CUSTOMER_ID = '01JQZ000000000000000CUST'
const CATALOG_ID = '01JQZ0000000000000CATA01'
const ITEMS = `${API}/customers/${CUSTOMER_ID}/catalogs/${CATALOG_ID}/items`

const item = (overrides: Record<string, unknown>) => ({
  id: '01JQZ00000000000000ITEM1',
  catalogId: CATALOG_ID,
  articleCode: 'ART-1',
  barcode: null,
  name: 'Carton renforcé',
  description: null,
  weight: 10,
  volume: 0.5,
  length: null,
  width: null,
  height: null,
  assemblyTimeMinutes: null,
  status: 'active',
  createdAt: '2026-02-01T10:00:00.000000Z',
  updatedAt: '2026-02-01T10:00:00.000000Z',
  ...overrides,
})

const render = () =>
  renderWithProviders(<CatalogItemTable customerId={CUSTOMER_ID} catalogId={CATALOG_ID} />, {
    membership: withPermissions(['catalogs.view', 'catalogs.update']),
  })

/**
 * Temps de montage d'un article de catalogue.
 *
 * Certains articles se posent, d'autres se montent. Le temps appartient à
 * l'article — il est connu avant qu'aucune commande n'existe.
 */
describe('temps de montage d’un article', () => {
  it('affiche les minutes de montage dans le tableau', async () => {
    server.use(
      http.get(ITEMS, () =>
        HttpResponse.json(
          paginated([item({ name: 'Canapé modulaire', assemblyTimeMinutes: 45 })]),
        ),
      ),
    )

    render()

    expect(await screen.findByText('Canapé modulaire')).toBeInTheDocument()
    expect(screen.getByText('45 min')).toBeInTheDocument()
  })

  /** Un article sans montage renseigné n'est pas un montage de zéro minute. */
  it('montre un tiret quand le montage n’est pas renseigné', async () => {
    server.use(http.get(ITEMS, () => HttpResponse.json(paginated([item({})]))))

    render()

    await screen.findByText('Carton renforcé')
    expect(screen.queryByText('0 min')).not.toBeInTheDocument()
  })

  it('envoie le montage saisi à la création', async () => {
    let body: unknown = null
    server.use(
      http.get(ITEMS, () => HttpResponse.json(paginated([]))),
      http.post(ITEMS, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: item({}), meta: [] }, { status: 201 })
      }),
    )

    render()

    await userEvent.click(await screen.findByRole('button', { name: /Nouvel article/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(dialog.getByLabelText(/^Code article/), 'ART-MONTAGE')
    await userEvent.type(dialog.getByLabelText(/^Nom/), 'Canapé modulaire')
    await userEvent.type(dialog.getByLabelText(/^Temps de montage/), '45')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({ articleCode: 'ART-MONTAGE', assemblyTimeMinutes: 45 })
  })

  /** Laissé vide, le champ part à `null` — jamais à `0`. */
  it('envoie null quand le champ reste vide', async () => {
    let body: unknown = null
    server.use(
      http.get(ITEMS, () => HttpResponse.json(paginated([]))),
      http.post(ITEMS, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: item({}), meta: [] }, { status: 201 })
      }),
    )

    render()

    await userEvent.click(await screen.findByRole('button', { name: /Nouvel article/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(dialog.getByLabelText(/^Code article/), 'ART-SIMPLE')
    await userEvent.type(dialog.getByLabelText(/^Nom/), 'Carton')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect((body as { assemblyTimeMinutes: number | null }).assemblyTimeMinutes).toBeNull()
  })
})
