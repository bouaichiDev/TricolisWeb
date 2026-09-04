import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { CommunicationRuleListPage } from './CommunicationRuleListPage'

const RULE_ID = '01JQZ0000000000000RULE001'
const TEMPLATE_ID = '01JQZ0000000000000TMPL001'

const emailTemplate = {
  id: TEMPLATE_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  customerId: null,
  serviceId: null,
  scope: 'global',
  code: 'DELIVERY_OK',
  name: 'Livraison terminée',
  channel: 'email',
  templateType: 'delivery_confirmation',
  subjectTemplate: 'Livrée',
  bodyTemplate: 'Bonjour.',
  bodyFormat: 'text',
  language: 'fr',
  availableVariables: [],
  isDefault: false,
  isActive: true,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
}

/** Un document : il n'a pas de canal, et ne doit donc pas être proposé. */
const invoiceTemplate = {
  ...emailTemplate,
  id: '01JQZ0000000000000TMPL002',
  code: 'INVOICE_DEFAULT',
  name: 'Facture standard',
  channel: null,
  templateType: 'invoice',
  subjectTemplate: null,
}

const rule = (overrides: Record<string, unknown> = {}) => ({
  id: RULE_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  serviceId: null,
  serviceName: null,
  templateId: TEMPLATE_ID,
  template: { id: TEMPLATE_ID, code: 'DELIVERY_OK', name: 'Livraison terminée', channel: 'email' },
  eventType: 'service_completed',
  recipientRole: 'delivery_contact',
  delayValue: 10,
  delayUnit: 'minutes',
  conditions: null,
  isAutomatic: true,
  isActive: true,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
  ...overrides,
})

function render(permissions: string[], rules: unknown[] = [rule()]) {
  server.use(
    http.get(`${API}/communication-rules`, () => HttpResponse.json(paginated(rules))),
    http.get(`${API}/templates`, () =>
      HttpResponse.json(paginated([emailTemplate, invoiceTemplate])),
    ),
    http.get(`${API}/services`, () => HttpResponse.json(paginated([]))),
  )

  return renderWithProviders(<CommunicationRuleListPage />, {
    membership: withPermissions(permissions),
  })
}

describe('règles de communication', () => {
  it('liste une règle avec son événement, son modèle et son délai', async () => {
    render(['communication_rules.view'])

    expect(await screen.findByText('Prestation terminée')).toBeInTheDocument()
    expect(screen.getByText('Livraison terminée')).toBeInTheDocument()
    expect(screen.getByText('Contact de livraison')).toBeInTheDocument()
    expect(screen.getByText('10 minutes')).toBeInTheDocument()
  })

  /**
   * Trois evenements sur onze sont emis. Une regle qui en vise un autre porte
   * la mention : le taire ferait attendre un message qui ne partira pas.
   */
  it('marque une règle dont l’événement n’est pas encore émis', async () => {
    // `service_completed` n'est pas cable : la regle est enregistree, mais
    // rien ne la declenchera.
    render(['communication_rules.view'])

    await screen.findByText('Prestation terminée')

    expect(screen.getByText('Non émis')).toBeInTheDocument()
  })

  it('ne signale rien sur un événement réellement émis', async () => {
    render(['communication_rules.view'], [rule({ eventType: 'order_cancelled' })])

    await screen.findByText('Commande annulée')

    expect(screen.queryByText('Non émis')).not.toBeInTheDocument()
  })

  it('masque création, modification et suppression sans les permissions', async () => {
    render(['communication_rules.view'])

    await screen.findByText('Prestation terminée')

    expect(screen.queryByRole('button', { name: /Nouvelle règle/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Modifier' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })
})

describe('création d’une règle', () => {
  /** Le canal vient du modèle : le §158 interdit de le porter sur la règle. */
  it('n’envoie pas de canal', async () => {
    let body: unknown = null
    render(['communication_rules.view', 'communication_rules.create'])

    server.use(
      http.post(`${API}/communication-rules`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: rule(), meta: [] }, { status: 201 })
      }),
    )

    await userEvent.click(await screen.findByRole('button', { name: /Nouvelle règle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByLabelText(/^Modèle/))
    await userEvent.click(await screen.findByRole('option', { name: /Livraison terminée/ }))
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({ templateId: TEMPLATE_ID, serviceId: null, conditions: null })
    expect(body).not.toHaveProperty('channel')
  })

  /** Une facture n'a pas de canal par où partir : elle ne peut pas être envoyée. */
  it('ne propose jamais un modèle de facture', async () => {
    render(['communication_rules.view', 'communication_rules.create'])

    await userEvent.click(await screen.findByRole('button', { name: /Nouvelle règle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByLabelText(/^Modèle/))

    expect(await screen.findByRole('option', { name: /Livraison terminée/ })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: /Facture standard/ })).not.toBeInTheDocument()
  })

  /**
   * Une règle automatique doit résoudre son destinataire depuis la commande ;
   * `custom` n'a aucune source pour cela, et la règle ne produirait rien.
   */
  it('ne propose pas un destinataire impossible à résoudre', async () => {
    render(['communication_rules.view', 'communication_rules.create'])

    await userEvent.click(await screen.findByRole('button', { name: /Nouvelle règle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByLabelText(/^Destinataire/))

    expect(await screen.findByRole('option', { name: 'Client' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Coordonnées saisies' })).not.toBeInTheDocument()
  })

  /** Le schéma est une conjonction plate : une clé `all`, une liste. */
  it('envoie les conditions au format que l’évaluateur accepte', async () => {
    let body: unknown = null
    render(['communication_rules.view', 'communication_rules.create'])

    server.use(
      http.post(`${API}/communication-rules`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: rule(), meta: [] }, { status: 201 })
      }),
    )

    await userEvent.click(await screen.findByRole('button', { name: /Nouvelle règle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByLabelText(/^Modèle/))
    await userEvent.click(await screen.findByRole('option', { name: /Livraison terminée/ }))

    await userEvent.click(dialog.getByRole('button', { name: /Ajouter une condition/ }))
    await userEvent.type(dialog.getByLabelText('Champ'), 'order_status')
    await userEvent.type(dialog.getByLabelText('Valeur'), 'confirmed')

    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      conditions: { all: [{ field: 'order_status', operator: 'eq', value: 'confirmed' }] },
    })
  })

  /** Le motif du serveur : ni point, ni chemin, ni parenthèse. */
  it('refuse un champ de condition portant un chemin', async () => {
    render(['communication_rules.view', 'communication_rules.create'])

    await userEvent.click(await screen.findByRole('button', { name: /Nouvelle règle/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('button', { name: /Ajouter une condition/ }))
    await userEvent.type(dialog.getByLabelText('Champ'), 'order.customer.secret')

    expect(await screen.findByText(/Minuscules, chiffres et tirets bas/)).toBeInTheDocument()
    expect(dialog.getByRole('button', { name: 'Enregistrer' })).toBeDisabled()
  })
})
