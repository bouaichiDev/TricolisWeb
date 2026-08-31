import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { ExportConfigurationListPage } from './ExportConfigurationListPage'

const CUSTOMER_ID = '01JQZ0000000000000CUSTO1'

const configuration = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ0000000000000CONF01',
  customerId: CUSTOMER_ID,
  name: 'API Migros',
  exportType: 'invoice',
  format: 'json',
  transport: 'rest_api',
  host: 'https://facturation.migros.example',
  port: null,
  username: 'tricolis',
  hasPassword: true,
  remoteDirectory: null,
  fileNamePattern: '{invoiceNumber}',
  encoding: 'UTF-8',
  frequency: 'on_invoice_closed',
  settings: null,
  isActive: true,
  ...overrides,
})

function render(configurations = [configuration()]) {
  const updates: Record<string, unknown>[] = []
  const creates: Record<string, unknown>[] = []

  server.use(
    http.get(`${API}/customers`, () =>
      HttpResponse.json(
        paginated([
          {
            id: CUSTOMER_ID,
            code: 'MIG',
            name: 'Migros Genève',
            status: 'active',
            organizationId: '01JQZ0000000000000000ORG1',
          },
        ]),
      ),
    ),
    http.get(`${API}/customers/:id/export-configurations`, () =>
      HttpResponse.json(paginated(configurations)),
    ),
    http.post(`${API}/customers/:id/export-configurations`, async ({ request }) => {
      creates.push((await request.json()) as Record<string, unknown>)

      return HttpResponse.json({ data: configuration() }, { status: 201 })
    }),
    http.patch(`${API}/customer-export-configurations/:id`, async ({ request }) => {
      updates.push((await request.json()) as Record<string, unknown>)

      return HttpResponse.json({ data: configuration() })
    }),
  )

  renderWithProviders(<ExportConfigurationListPage />, {
    membership: withPermissions([
      'customer_export_configurations.view',
      'customer_export_configurations.create',
      'customer_export_configurations.update',
      'customers.view',
    ]),
  })

  return { creates, updates }
}

async function chooseCustomer() {
  await userEvent.click(screen.getAllByRole('combobox')[0])
  await userEvent.click(await screen.findByRole('option', { name: /Migros/ }))
}

describe('destinations d’export', () => {
  /** §113 : une destination appartient à un client, et ne sert jamais à un autre. */
  it('ne charge rien tant qu’aucun client n’est choisi', async () => {
    render()

    expect(await screen.findByText(/ne servent jamais à un autre/)).toBeInTheDocument()
  })

  it('liste les destinations du client', async () => {
    render()

    await chooseCustomer()

    expect(await screen.findByText('API Migros')).toBeInTheDocument()
    expect(screen.getByText('API REST')).toBeInTheDocument()
  })

  /**
   * **Le secret ne se relit pas** (§124). Le champ est vide à l'ouverture, et
   * le laisser vide conserve ce qui est enregistré — l'omettre est la seule
   * façon de dire « inchangé ».
   */
  it('n’envoie pas de mot de passe quand le champ reste vide', async () => {
    const { updates } = render()

    await chooseCustomer()
    await userEvent.click(await screen.findByRole('button', { name: 'Modifier' }))

    const dialog = await screen.findByRole('dialog')
    // Sur une API REST en jeton porteur, le champ s'appelle « Jeton » : ce
    // qu'on y colle n'est pas un mot de passe, et le nommer ainsi ferait
    // chercher un champ qui n'existe pas.
    expect(within(dialog).getByLabelText('Jeton')).toHaveValue('')

    await userEvent.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(updates).toHaveLength(1))
    expect(updates[0]).not.toHaveProperty('password')
  })

  /**
   * §32 : un format n'est proposé que si son générateur existe. Les quatre du
   * modèle en ont un désormais ; aucun ne crée une destination qui échouerait
   * à la clôture, loin de cet écran.
   */
  it('propose les quatre formats du modèle', async () => {
    render()

    await chooseCustomer()
    await userEvent.click(await screen.findByRole('button', { name: 'Nouvelle destination' }))

    const dialog = await screen.findByRole('dialog')
    await userEvent.click(within(dialog).getByLabelText('Format'))

    expect(await screen.findByRole('option', { name: 'JSON' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'XML' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'CSV' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'PDF' })).toBeInTheDocument()
  })

  /** La clôture ne consulte que les destinations d'un envoi de facture. */
  it('déclare le type et la fréquence que la clôture consulte', async () => {
    const { creates } = render()

    await chooseCustomer()
    await userEvent.click(await screen.findByRole('button', { name: 'Nouvelle destination' }))

    const dialog = await screen.findByRole('dialog')
    await userEvent.type(within(dialog).getByLabelText('Nom'), 'API Coop')
    await userEvent.type(within(dialog).getByLabelText('URL de l’API'), 'https://coop.example')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(creates).toHaveLength(1))
    expect(creates[0].exportType).toBe('invoice')
    expect(creates[0].frequency).toBe('on_invoice_closed')
  })

  /**
   * L'écran ne montre que ce que le transport retenu demande vraiment : tout
   * afficher donnerait une vingtaine de champs dont la moitié sans effet — et
   * remplie quand même.
   */
  it('demande une URL de jeton en OAuth2, et rien avant', async () => {
    render()

    await chooseCustomer()
    await userEvent.click(await screen.findByRole('button', { name: 'Nouvelle destination' }))

    const dialog = await screen.findByRole('dialog')

    expect(within(dialog).queryByLabelText('URL du serveur de jetons')).not.toBeInTheDocument()

    await userEvent.click(within(dialog).getByLabelText('Authentification'))
    await userEvent.click(await screen.findByRole('option', { name: /OAuth2/ }))

    expect(within(dialog).getByLabelText('URL du serveur de jetons')).toBeInTheDocument()
    expect(within(dialog).getByLabelText('Portée (scope)')).toBeInTheDocument()
  })

  /** Un envoi par courriel n'a rien à joindre : il a des destinataires. */
  it('échange la connexion contre des destinataires en mode courriel', async () => {
    const { creates } = render()

    await chooseCustomer()
    await userEvent.click(await screen.findByRole('button', { name: 'Nouvelle destination' }))

    const dialog = await screen.findByRole('dialog')
    await userEvent.click(within(dialog).getByLabelText('Transport'))
    await userEvent.click(await screen.findByRole('option', { name: 'E-mail' }))

    expect(within(dialog).queryByLabelText('URL de l’API')).not.toBeInTheDocument()
    expect(within(dialog).queryByLabelText('Hôte')).not.toBeInTheDocument()

    await userEvent.type(within(dialog).getByLabelText('Nom'), 'Compta Coop')
    await userEvent.type(
      within(dialog).getByLabelText('Destinataires'),
      'compta@coop.example',
    )
    await userEvent.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(creates).toHaveLength(1))
    expect(creates[0].transport).toBe('email')
    expect(creates[0].settings).toMatchObject({ recipients: 'compta@coop.example' })
  })

  /** Sans destinataire, l'enregistrement resterait sans effet à la clôture. */
  it('retient l’enregistrement d’un courriel sans destinataire', async () => {
    render()

    await chooseCustomer()
    await userEvent.click(await screen.findByRole('button', { name: 'Nouvelle destination' }))

    const dialog = await screen.findByRole('dialog')
    await userEvent.click(within(dialog).getByLabelText('Transport'))
    await userEvent.click(await screen.findByRole('option', { name: 'E-mail' }))
    await userEvent.type(within(dialog).getByLabelText('Nom'), 'Compta Coop')

    expect(within(dialog).getByRole('button', { name: 'Enregistrer' })).toBeDisabled()
  })

  /** Le CSV se lit dans un tableur : le séparateur décide s'il est lisible. */
  it('propose le séparateur seulement pour un CSV', async () => {
    render()

    await chooseCustomer()
    await userEvent.click(await screen.findByRole('button', { name: 'Nouvelle destination' }))

    const dialog = await screen.findByRole('dialog')

    expect(within(dialog).queryByLabelText('Séparateur CSV')).not.toBeInTheDocument()

    await userEvent.click(within(dialog).getByLabelText('Format'))
    await userEvent.click(await screen.findByRole('option', { name: 'CSV' }))

    expect(within(dialog).getByLabelText('Séparateur CSV')).toBeInTheDocument()
  })
})
