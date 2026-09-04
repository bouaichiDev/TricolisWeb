import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { ProviderForm } from './ProviderForm'
import type { Provider } from '../types/provider'

const ADDRESS_ID = '01JQZ0000000000000ADDR01'
const CONTACT_ID = '01JQZ0000000000000CONT01'

const address = {
  id: ADDRESS_ID,
  code: null,
  name: 'Entrepôt Casablanca',
  addressLine1: '12 rue des Oliviers',
  postalCode: '20000',
  city: 'Casablanca',
  status: 'active',
}

const contact = {
  id: CONTACT_ID,
  firstName: 'Sophie',
  lastName: 'Bernard',
  email: 'sophie@example.test',
  phone: null,
  status: 'active',
}

const status = {
  id: '01JQZ000000000000STAP01',
  source: 'provider',
  status: 1,
  code: 'active',
  label: 'Actif',
  icon: null,
  active: true,
  isToSend: false,
  allowsContentChanges: false,
  requiresReason: false,
  position: 10,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
}

function render(provider?: Provider) {
  const sent: unknown[] = []

  server.use(
    http.get(`${API}/statuses`, () => HttpResponse.json(paginated([status]))),
    http.get(`${API}/addresses`, () => HttpResponse.json(paginated([address]))),
    http.get(`${API}/contacts`, () => HttpResponse.json(paginated([contact]))),
  )

  renderWithProviders(
    <ProviderForm
      provider={provider}
      isPending={false}
      onCancel={() => {}}
      onSubmit={async (payload) => {
        sent.push(payload)
      }}
    />,
    { membership: withPermissions(['providers.create', 'providers.update']) },
  )

  return sent
}

/**
 * L'adresse et le contact d'un fournisseur sont deux colonnes directes, pas des
 * liaisons polymorphes : le formulaire en choisit une de chaque, et sait les
 * détacher.
 */
describe('formulaire fournisseur', () => {
  it('rattache l’adresse et le contact choisis', async () => {
    const sent = render()

    await userEvent.type(await screen.findByLabelText(/^Code/), 'TRANS-01')
    await userEvent.type(screen.getByLabelText(/^Nom/), 'Transports Atlas')

    await userEvent.click(screen.getByRole('combobox', { name: /Adresse/ }))
    await userEvent.click(await screen.findByRole('option', { name: /Entrepôt Casablanca/ }))

    await userEvent.click(screen.getByRole('combobox', { name: /Contact/ }))
    await userEvent.click(await screen.findByRole('option', { name: /Sophie Bernard/ }))

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toMatchObject({
      code: 'TRANS-01',
      name: 'Transports Atlas',
      status: 'active',
      addressId: ADDRESS_ID,
      contactId: CONTACT_ID,
    })
  })

  /** « Aucune » vaut `null`, pas la chaîne « none » ni une chaîne vide. */
  it('détache l’adresse et le contact', async () => {
    const sent = render({
      id: '01JQZ0000000000000PROV01',
      organizationId: '01JQZ0000000000000000ORG1',
      addressId: ADDRESS_ID,
      contactId: CONTACT_ID,
      code: 'TRANS-01',
      name: 'Transports Atlas',
      status: 'active',
    })

    await userEvent.click(await screen.findByRole('combobox', { name: /Adresse/ }))
    await userEvent.click(await screen.findByRole('option', { name: /Aucune adresse/ }))

    await userEvent.click(screen.getByRole('combobox', { name: /Contact/ }))
    await userEvent.click(await screen.findByRole('option', { name: /Aucun contact/ }))

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toMatchObject({ addressId: null, contactId: null })
  })
})
