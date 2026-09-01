import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { ImportRunPanel } from './ImportRunPanel'
import { IMPORT_CONFIG_ID } from '../testSupport'

const AGENCY_ID = '01JQZ0000000000000000AGY1'

function serveScope() {
  server.use(
    http.get(`${API}/agencies`, () =>
      HttpResponse.json(
        paginated([
          {
            id: AGENCY_ID,
            organizationId: '01JQZ0000000000000000ORG1',
            code: 'AG01',
            name: 'Agence Nord',
            status: 'active',
            createdAt: '2026-02-01T10:00:00.000000Z',
            updatedAt: '2026-02-01T10:00:00.000000Z',
          },
        ]),
      ),
    ),
    http.get(`${API}/agencies/${AGENCY_ID}/depots`, () => HttpResponse.json(paginated([]))),
  )
}

const render = (hasMapping = true, isActive = true) =>
  renderWithProviders(
    <ImportRunPanel
      configurationId={IMPORT_CONFIG_ID}
      hasMapping={hasMapping}
      isActive={isActive}
    />,
    { membership: withPermissions(['customer_import_configurations.view', 'orders.create']) },
  )

const pickAgency = async () => {
  await userEvent.click(await screen.findByLabelText(/^Agence/))
  await userEvent.click(await screen.findByRole('option', { name: /Agence Nord/ }))
}

const upload = async () => {
  const input = document.querySelector('input[type="file"]') as HTMLInputElement

  await userEvent.upload(input, new File(['REF\nCMD-1\n'], 'import.csv', { type: 'text/csv' }))
}

describe('import réel d’un fichier', () => {
  /**
   * `orders.agency_id` est `NOT NULL` : le bouton reste inerte tant qu'aucune
   * agence n'est choisie, et l'écran dit pourquoi.
   */
  it('exige une agence avant de laisser importer', async () => {
    serveScope()
    render()

    expect(await screen.findByRole('button', { name: /Choisir et importer/ })).toBeDisabled()
    expect(screen.getByText(/Choisissez d’abord l’agence/)).toBeInTheDocument()

    await pickAgency()

    await waitFor(() =>
      expect(screen.getByRole('button', { name: /Choisir et importer/ })).toBeEnabled(),
    )
  })

  it('reste inerte sur une configuration désactivée', async () => {
    serveScope()
    render(true, false)

    expect(await screen.findByText(/configuration est désactivée/)).toBeInTheDocument()
  })

  it('envoie l’agence choisie avec le fichier', async () => {
    serveScope()

    const agencies: string[] = []
    server.use(
      http.post(
        `${API}/customer-import-configurations/${IMPORT_CONFIG_ID}/import`,
        async ({ request }) => {
          const form = await request.formData()
          agencies.push(String(form.get('agencyId')))

          return HttpResponse.json(
            { data: { rowCount: 1, orders: [] }, meta: [] },
            { status: 201 },
          )
        },
      ),
    )

    render()
    await pickAgency()
    await upload()

    await waitFor(() => expect(agencies).toEqual([AGENCY_ID]))
  })

  it('liste les commandes créées', async () => {
    serveScope()
    server.use(
      http.post(`${API}/customer-import-configurations/${IMPORT_CONFIG_ID}/import`, () =>
        HttpResponse.json(
          {
            data: {
              rowCount: 3,
              orders: [
                { id: 'o1', orderNumber: 'CMD-0001', externalReference: 'REF-1' },
                { id: 'o2', orderNumber: 'CMD-0002', externalReference: 'REF-2' },
              ],
            },
            meta: [],
          },
          { status: 201 },
        ),
      ),
    )

    render()
    await pickAgency()
    await upload()

    expect(await screen.findByText('2 commandes créées.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /CMD-0001/ })).toBeInTheDocument()
  })

  /**
   * Tout ou rien : le refus détaille quelle commande du fichier est fautive, et
   * dit explicitement que rien n'a été écrit.
   */
  it('détaille le refus et affirme qu’aucune commande n’a été créée', async () => {
    serveScope()
    server.use(
      http.post(`${API}/customer-import-configurations/${IMPORT_CONFIG_ID}/import`, () =>
        HttpResponse.json(
          {
            message: 'The orders.1.lines.0.quantity field is required.',
            errors: { 'orders.1.lines.0.quantity': ['La quantité est obligatoire.'] },
          },
          { status: 422 },
        ),
      ),
    )

    render()
    await pickAgency()
    await upload()

    expect(await screen.findByText('orders.1.lines.0.quantity')).toBeInTheDocument()
    expect(screen.getByText(/Aucune commande n’a été créée/)).toBeInTheDocument()
  })
})
