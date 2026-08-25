import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { CommunicationTemplateListPage } from './CommunicationTemplateListPage'

const TEMPLATE_ID = '01JQZ0000000000000TMPL01'

const template = (overrides: Record<string, unknown> = {}) => ({
  id: TEMPLATE_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  serviceId: null,
  code: 'CUSTOMER_ABSENT_EMAIL',
  name: 'Client absent',
  channel: 'email',
  templateType: 'custom',
  subjectTemplate: 'Absence lors de notre passage - {{orderNumber}}',
  bodyTemplate: 'Bonjour, nous sommes passés le {{deliveryDate}}.',
  language: 'fr',
  availableVariables: ['orderNumber'],
  isDefault: false,
  isActive: true,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
  ...overrides,
})

function render(permissions: string[], templates: unknown[] = [template()]) {
  server.use(
    http.get(`${API}/communication-templates`, () => HttpResponse.json(paginated(templates))),
  )

  return renderWithProviders(<CommunicationTemplateListPage />, {
    membership: withPermissions(permissions),
  })
}

describe('modèles de communication', () => {
  it('liste les modèles avec leur canal, leur type et leur langue', async () => {
    render(['communication_templates.view'])

    expect(await screen.findByText('Client absent')).toBeInTheDocument()
    expect(screen.getByText('CUSTOMER_ABSENT_EMAIL')).toBeInTheDocument()
    expect(screen.getByText('E-mail')).toBeInTheDocument()
    expect(screen.getByText('Personnalisé')).toBeInTheDocument()
    expect(screen.getByText('FR')).toBeInTheDocument()
  })

  /**
   * `CUSTOMER_ABSENT_EMAIL` est un **code**, et `templateType` reste `custom` :
   * le §20 interdit d'ajouter `CUSTOMER_ABSENT` aux types.
   */
  it('crée un modèle « Client absent » sans inventer de type', async () => {
    let body: unknown = null
    render(['communication_templates.view', 'communication_templates.create'])

    server.use(
      http.post(`${API}/communication-templates`, async ({ request }) => {
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
    })
  })

  /**
   * `StoreCommunicationTemplateRequest` rend le sujet requis pour un e-mail et
   * pas pour un SMS. Le demander toujours ferait saisir une valeur inutile.
   */
  it('n’exige le sujet que sur les canaux qui en ont un', async () => {
    let body: unknown = null
    render(['communication_templates.view', 'communication_templates.create'])

    server.use(
      http.post(`${API}/communication-templates`, async ({ request }) => {
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
    render(['communication_templates.view', 'communication_templates.update'])

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier' }))

    // Le corps emploie {{deliveryDate}}, absent d'availableVariables. Le nom
    // figure aussi dans l'apercu : l'assertion se borne a l'alerte.
    const warning = await screen.findByText(/Ces variables ne sont pas déclarées/)

    expect(warning).toHaveTextContent('{{deliveryDate}}')
    expect(warning).not.toHaveTextContent('{{orderNumber}}')
  })

  /** Le code identifie le modèle : le renommer romprait la référence. */
  it('verrouille le code en modification', async () => {
    render(['communication_templates.view', 'communication_templates.update'])

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier' }))

    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.getByLabelText(/^Code/)).toBeDisabled()
  })

  it('masque création, modification et suppression sans les permissions', async () => {
    render(['communication_templates.view'])

    await screen.findByText('Client absent')

    expect(screen.queryByRole('button', { name: /Nouveau modèle/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Modifier' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })
})
