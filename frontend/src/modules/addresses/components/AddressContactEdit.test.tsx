import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { EntityAddressesPanel } from './EntityAddressesPanel'
import {
  addressesHandler,
  CUSTOMER_ID,
  makeAddress,
  makeAddressContact,
  makeLink,
} from './addressTestData'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const address = makeAddress('a1', { links: [makeLink('l1', 'delivery')] })

const editor = withPermissions([
  'addresses.view',
  'addresses.update',
  'contacts.create',
  'contacts.update',
])

function panel(membership = editor) {
  renderWithProviders(<EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} />, {
    membership,
  })
}

/**
 * Le nom et le téléphone appartiennent au **contact** ; le rôle et le drapeau
 * principal appartiennent à la **liaison**. Deux ressources, deux routes — et
 * la liaison n'ayant pas de `PATCH`, elle est refaite.
 */
describe('modification d’un contact', () => {
  function withContact() {
    return [
      addressesHandler([address]),
      http.get(`${API}/addresses/:id/contacts`, () =>
        HttpResponse.json({ data: [makeAddressContact()], meta: [] }),
      ),
    ]
  }

  it('propose la modification avec contacts.update', async () => {
    server.use(...withContact())
    panel(withPermissions(['addresses.view', 'addresses.update', 'contacts.update']))

    expect(await screen.findByRole('button', { name: 'Modifier le contact' })).toBeInTheDocument()
  })

  it('masque la modification sans contacts.update', async () => {
    server.use(...withContact())
    panel(withPermissions(['addresses.view', 'addresses.update']))

    await screen.findByText('Sara Amrani')
    expect(screen.queryByRole('button', { name: 'Modifier le contact' })).not.toBeInTheDocument()
  })

  it('pré-remplit le formulaire avec le contact existant', async () => {
    server.use(...withContact())
    panel()

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier le contact' }))

    expect(screen.getByLabelText(/^Prénom \*/)).toHaveValue('Sara')
    expect(screen.getByLabelText(/^Adresse e-mail/)).toHaveValue('sara@example.test')
  })

  it('modifie le contact sans refaire la liaison quand le rôle ne change pas', async () => {
    const calls: string[] = []
    server.use(
      ...withContact(),
      http.patch(`${API}/contacts/:id`, () => {
        calls.push('patch-contact')

        return HttpResponse.json({ data: { id: 'ct1' }, meta: [] })
      }),
    )
    panel()

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier le contact' }))
    await userEvent.clear(screen.getByLabelText(/^Prénom \*/))
    await userEvent.type(screen.getByLabelText(/^Prénom \*/), 'Sarah')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(calls).toEqual(['patch-contact'])
    })
  })

  /**
   * Le nouveau rattachement est créé **avant** que l'ancien soit retiré : un
   * échec au milieu laisserait sinon le contact détaché de l'adresse.
   */
  it('refait la liaison quand le rôle change, dans le bon ordre', async () => {
    const calls: string[] = []
    server.use(
      ...withContact(),
      http.patch(`${API}/contacts/:id`, () => {
        calls.push('patch-contact')

        return HttpResponse.json({ data: { id: 'ct1' }, meta: [] })
      }),
      http.post(`${API}/addresses/:id/contacts`, () => {
        calls.push('attach')

        return HttpResponse.json({ data: { id: 'new' }, meta: [] }, { status: 201 })
      }),
      http.delete(`${API}/addresses/:id/contacts/:link`, () => {
        calls.push('detach')

        return new HttpResponse(null, { status: 204 })
      }),
    )
    panel()

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier le contact' }))
    await userEvent.click(screen.getByLabelText('Principal'))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(calls).toEqual(['patch-contact', 'attach', 'detach'])
    })
  })
})
