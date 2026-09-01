import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { TemplateListPage } from './TemplateListPage'

const TEMPLATE_ID = '01JQZ0000000000000TMPL01'
const CUSTOMER_ID = '01JQZ00000000000000CUST1'

const template = (overrides: Record<string, unknown> = {}) => ({
  id: TEMPLATE_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  customerId: null,
  customerName: null,
  serviceId: null,
  serviceName: null,
  scope: 'global',
  code: 'CUSTOMER_ABSENT_EMAIL',
  name: 'Client absent',
  channel: 'email',
  templateType: 'custom',
  subjectTemplate: 'Absence lors de notre passage - {{orderNumber}}',
  bodyTemplate: 'Bonjour, nous sommes passés le {{deliveryDate}}.',
  language: 'fr',
  bodyFormat: 'text',
  availableVariables: ['orderNumber'],
  isDefault: false,
  isActive: true,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
  ...overrides,
})

/**
 * L'écran choisit le client et la prestation d'un modèle : les deux listes
 * sont donc interrogées à l'ouverture, et `onUnhandledRequest: 'error'` les
 * exige déclarées ici.
 */
function render(permissions: string[], templates: unknown[] = [template()]) {
  server.use(
    http.get(`${API}/templates`, () => HttpResponse.json(paginated(templates))),
    http.get(`${API}/customers`, () =>
      HttpResponse.json(paginated([{ id: CUSTOMER_ID, code: 'IKEA', name: 'IKEA' }])),
    ),
    http.get(`${API}/services`, () => HttpResponse.json(paginated([]))),
  )

  return renderWithProviders(<TemplateListPage />, {
    membership: withPermissions(permissions),
  })
}

describe('modèles', () => {
  it('liste les modèles avec leur canal, leur type et leur langue', async () => {
    render(['templates.view'])

    expect(await screen.findByText('Client absent')).toBeInTheDocument()
    expect(screen.getByText('CUSTOMER_ABSENT_EMAIL')).toBeInTheDocument()
    expect(screen.getByText('E-mail')).toBeInTheDocument()
    expect(screen.getByText('FR')).toBeInTheDocument()
  })

  /** Un modèle sans client vaut pour toute l'organisation : le dire. */
  it('marque un modèle sans client comme celui du transporteur', async () => {
    render(['templates.view'])

    await screen.findByText('Client absent')

    expect(screen.getAllByText('Transporteur').length).toBeGreaterThan(0)
  })

  /**
   * `CUSTOMER_ABSENT_EMAIL` est un **code**, et `templateType` reste `custom` :
   * le §20 interdit d'ajouter `CUSTOMER_ABSENT` aux types.
   */
  it('crée un modèle « Client absent » sans inventer de type', async () => {
    let body: unknown = null
    render(['templates.view', 'templates.create'])

    server.use(
      http.post(`${API}/templates`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: template(), meta: [] }, { status: 201 })
      }),
    )

    await userEvent.click(await screen.findByRole('button', { name: /Nouveau modèle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(dialog.getByLabelText(/^Code/), 'CUSTOMER_NO_RESPONSE')
    await userEvent.type(dialog.getByLabelText(/^Nom/), 'Client ne répond pas')
    await userEvent.type(dialog.getByLabelText(/^Sujet/), 'Nous n’avons pas pu vous joindre')
    await userEvent.type(dialog.getByLabelText(/^Message/), 'Bonjour,')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      code: 'CUSTOMER_NO_RESPONSE',
      channel: 'email',
      templateType: 'custom',
      language: 'fr',
      customerId: null,
    })
  })

  /**
   * `StoreTemplateRequest` rend le sujet requis pour un e-mail et pas pour un
   * SMS. Le demander toujours ferait saisir une valeur inutile.
   */
  it('n’exige le sujet que sur les canaux qui en ont un', async () => {
    let body: unknown = null
    render(['templates.view', 'templates.create'])

    server.use(
      http.post(`${API}/templates`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: template(), meta: [] }, { status: 201 })
      }),
    )

    await userEvent.click(await screen.findByRole('button', { name: /Nouveau modèle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByLabelText(/^Canal/))
    await userEvent.click(await screen.findByRole('option', { name: 'SMS' }))

    expect(dialog.queryByLabelText(/^Sujet/)).not.toBeInTheDocument()

    await userEvent.type(dialog.getByLabelText(/^Code/), 'SMS_RAPPEL')
    await userEvent.type(dialog.getByLabelText(/^Nom/), 'Rappel SMS')
    await userEvent.type(dialog.getByLabelText(/^Message/), 'Passage prévu demain.')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({ channel: 'sms', subjectTemplate: null })
  })

  /** Les variables non déclarées ne seront pas remplacées : le dire. */
  it('signale une variable employée mais non déclarée', async () => {
    render(['templates.view', 'templates.update'])

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier' }))

    // Le corps emploie {{deliveryDate}}, absent d'availableVariables. Le nom
    // figure aussi dans l'apercu : l'assertion se borne a l'alerte.
    const warning = await screen.findByText(/Ces variables ne sont pas déclarées/)

    expect(warning).toHaveTextContent('{{deliveryDate}}')
    expect(warning).not.toHaveTextContent('{{orderNumber}}')
  })

  /** Le code identifie le modèle : le renommer romprait la référence. */
  it('verrouille le code en modification', async () => {
    render(['templates.view', 'templates.update'])

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier' }))

    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.getByLabelText(/^Code/)).toBeDisabled()
  })

  it('masque création, modification et suppression sans les permissions', async () => {
    render(['templates.view'])

    await screen.findByText('Client absent')

    expect(screen.queryByRole('button', { name: /Nouveau modèle/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Modifier' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })
})

/**
 * Un e-mail se rédige souvent en HTML, un SMS jamais.
 *
 * Sans `bodyFormat`, le serveur ne savait pas s'il devait échapper le corps.
 */
describe('format du message', () => {
  it('propose le format sur un e-mail, jamais sur un SMS', async () => {
    render(['templates.view', 'templates.create'])

    await userEvent.click(await screen.findByRole('button', { name: /Nouveau modèle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.getByLabelText(/^Format du message/)).toBeInTheDocument()

    await userEvent.click(dialog.getByLabelText(/^Canal/))
    await userEvent.click(await screen.findByRole('option', { name: 'SMS' }))

    expect(dialog.queryByLabelText(/^Format du message/)).not.toBeInTheDocument()
  })

  it('envoie le format retenu', async () => {
    let body: unknown = null
    render(['templates.view', 'templates.create'])

    server.use(
      http.post(`${API}/templates`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: template(), meta: [] }, { status: 201 })
      }),
    )

    await userEvent.click(await screen.findByRole('button', { name: /Nouveau modèle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByLabelText(/^Format du message/))
    await userEvent.click(await screen.findByRole('option', { name: 'HTML' }))

    await userEvent.type(dialog.getByLabelText(/^Code/), 'RICHE')
    await userEvent.type(dialog.getByLabelText(/^Nom/), 'Modèle riche')
    await userEvent.type(dialog.getByLabelText(/^Sujet/), 'Sujet')
    await userEvent.type(dialog.getByLabelText(/^Message/), '<p>Bonjour</p>')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({ bodyFormat: 'html' })
  })

  /**
   * Le HTML se rend dans une iframe cloisonnée : un `<script>` glissé dans un
   * modèle s'exécuterait sinon chez tous ceux qui l'ouvrent.
   */
  it('rend l’aperçu HTML dans une iframe sans script', async () => {
    render(
      ['templates.view', 'templates.update'],
      [template({ bodyFormat: 'html', bodyTemplate: '<p>Bonjour</p>' })],
    )

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier' }))

    const frame = await screen.findByTitle('Aperçu')
    expect(frame).toHaveAttribute('sandbox', '')
    expect(frame).toHaveAttribute('srcdoc', '<p>Bonjour</p>')
  })
})

/**
 * Le modèle de facture est un **document** : le §0.7 interdit de lui inventer
 * un canal, et l'écran ne doit pas laisser en saisir un.
 */
/**
 * `order_number` ne dit rien a qui remplit le formulaire.
 *
 * Deviner les noms disponibles etait la cause d'un rendu en echec a l'envoi :
 * un modele declarant une variable que le serveur ne fournit pas ne produit
 * aucun message, et l'erreur ne se lit que dans le journal.
 */
describe('variables proposées', () => {
  it('propose les variables d’une commande avec leur libellé', async () => {
    render(['templates.view', 'templates.create'])

    await userEvent.click(await screen.findByRole('button', { name: /Nouveau modèle/ }))

    const dialog = within(await screen.findByRole('dialog'))

    expect(dialog.getByRole('button', { name: /N° commande/ })).toBeInTheDocument()
    expect(dialog.getByRole('button', { name: /Nom du client/ })).toBeInTheDocument()
  })

  it('déclare la variable quand on la choisit', async () => {
    render(['templates.view', 'templates.create'])

    await userEvent.click(await screen.findByRole('button', { name: /Nouveau modèle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('button', { name: /N° commande/ }))

    expect(dialog.getByText('{{order_number}}')).toBeInTheDocument()
  })

  /** Les chemins d'une facture ont leurs propres libellés. */
  it('propose les chemins d’une facture avec leur libellé', async () => {
    render(['templates.view', 'templates.create'])

    await userEvent.click(await screen.findByRole('button', { name: /Nouveau modèle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByLabelText(/^Type/))
    await userEvent.click(await screen.findByRole('option', { name: 'Facture' }))

    expect(dialog.getByRole('button', { name: /Total TTC/ })).toBeInTheDocument()
  })
})

describe('modèle de facture', () => {
  it('retire le canal et le sujet quand le type devient « Facture »', async () => {
    render(['templates.view', 'templates.create'])

    await userEvent.click(await screen.findByRole('button', { name: /Nouveau modèle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.getByLabelText(/^Canal/)).toBeInTheDocument()

    await userEvent.click(dialog.getByLabelText(/^Type/))
    await userEvent.click(await screen.findByRole('option', { name: 'Facture' }))

    expect(dialog.queryByLabelText(/^Canal/)).not.toBeInTheDocument()
    expect(dialog.queryByLabelText(/^Sujet/)).not.toBeInTheDocument()
  })

  it('envoie un canal nul et propose une mise en page de départ', async () => {
    let body: unknown = null
    render(['templates.view', 'templates.create'])

    server.use(
      http.post(`${API}/templates`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: template(), meta: [] }, { status: 201 })
      }),
    )

    await userEvent.click(await screen.findByRole('button', { name: /Nouveau modèle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByLabelText(/^Type/))
    await userEvent.click(await screen.findByRole('option', { name: 'Facture' }))

    await userEvent.type(dialog.getByLabelText(/^Code/), 'INVOICE_DEFAULT')
    await userEvent.type(dialog.getByLabelText(/^Nom/), 'Facture standard')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      templateType: 'invoice',
      channel: null,
      subjectTemplate: null,
      serviceId: null,
    })
    expect((body as { bodyTemplate: string }).bodyTemplate).toContain('{{#invoice.lines}}')
  })
})
