import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { ORGANIZATION_ID, paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderCreatePage } from '../../pages/OrderCreatePage'
import { goTo, pick } from '../../pages/wizardActions'
import { serveWizardScope } from '../../pages/wizardScope'

const NEW_ADDRESS_ID = '01JQZ00000000000000ADDR9'
const NEW_CONTACT_ID = '01JQZ0000000000000CONT99'

/**
 * Création d'une adresse de destination sans quitter la commande.
 *
 * `StoreOrderRequest` exige un `addressId` existant : l'adresse ne peut pas
 * voyager dans la charge utile. Elle est donc créée d'abord, puis désignée.
 */
const render = () =>
  renderWithProviders(<OrderCreatePage />, {
    membership: withPermissions([
      'orders.view',
      'orders.create',
      'addresses.create',
      'contacts.create',
    ]),
    route: '/orders/create',
  })

/**
 * Ouvre le dialogue et rend sa portee.
 *
 * « Adresse * » designe deux champs a l'ecran : le selecteur du service et la
 * premiere ligne du formulaire. La recherche est donc bornee au dialogue.
 */
async function openDialog() {
  await pick(/^Client/, /Client Alpha/)
  await pick(/^Agence/, /Agence Nord/)
  await goTo('Services')
  await userEvent.click(await screen.findByRole('button', { name: /Nouvelle adresse/ }))

  return within(await screen.findByRole('dialog'))
}

describe('nouvelle adresse d’un service', () => {
  /**
   * L'adresse ne rejoint **pas** le carnet du donneur d'ordre : une livraison
   * ponctuelle chez un tiers n'a pas à y entrer. Le modèle ne permet pas de la
   * rattacher à la commande — `StoreAddressRequest` n'accepte que organisation,
   * client, site, agence et dépôt — elle est donc portée par l'organisation.
   */
  it('crée l’adresse hors carnet client et la sélectionne', async () => {
    serveWizardScope({ catalogEnabled: false })

    let body: unknown = null
    server.use(
      http.post(`${API}/addresses`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json(
          { data: { id: NEW_ADDRESS_ID, name: 'Chantier Nord' }, meta: [] },
          { status: 201 },
        )
      }),
    )

    render()
    const dialog = await openDialog()

    await userEvent.type(dialog.getByLabelText('Adresse *'), '9 rue du Port')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())

    const payload = body as { addressLine1: string; entityType: string; entityId: string }

    expect(payload.addressLine1).toBe('9 rue du Port')
    expect(payload.entityType).toBe('organization')
    expect(payload.entityId).toBe(ORGANIZATION_ID)
  })

  /** La source bascule sur la liste hors carnet, seule à contenir l'adresse. */
  it('bascule la source pour montrer l’adresse créée', async () => {
    serveWizardScope({ catalogEnabled: false })

    const created = { id: NEW_ADDRESS_ID, name: 'Chantier Nord', city: 'Lille' }
    server.use(
      http.post(`${API}/addresses`, () =>
        HttpResponse.json({ data: created, meta: [] }, { status: 201 }),
      ),
      http.get(`${API}/addresses`, ({ request }) => {
        const entityType = new URL(request.url).searchParams.get('entityType')

        return HttpResponse.json(paginated(entityType === 'organization' ? [created] : []))
      }),
    )

    render()
    const dialog = await openDialog()

    await userEvent.type(dialog.getByLabelText('Adresse *'), '9 rue du Port')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    // POST, invalidation, refetch : trois allers-retours avant que le libelle
    // apparaisse. Le delai par defaut d'une seconde suffit sur une machine au
    // repos et pas sur une machine chargee — d'ou l'attente allongee, qui ne
    // relache aucune assertion.
    const wait = { timeout: 5000 }

    expect(
      await screen.findByText('Autres adresses (hors carnet client)', undefined, wait),
    ).toBeInTheDocument()
    expect(await screen.findByText('Chantier Nord', undefined, wait)).toBeInTheDocument()
  })

  /**
   * Sur un point de livraison, l'adresse sans le nom de qui reçoit ne sert à
   * rien : les deux se saisissent d'un seul tenant. Le contact est alors versé
   * dans le service, faute de quoi il faudrait le retaper juste en dessous.
   */
  it('verse le contact saisi dans le service, sans le redemander', async () => {
    serveWizardScope({ catalogEnabled: false })

    let contact: unknown = null
    let attached: unknown = null

    server.use(
      http.post(`${API}/addresses`, () =>
        HttpResponse.json({ data: { id: NEW_ADDRESS_ID }, meta: [] }, { status: 201 }),
      ),
      http.post(`${API}/contacts`, async ({ request }) => {
        contact = await request.json()
        return HttpResponse.json({ data: { id: NEW_CONTACT_ID }, meta: [] }, { status: 201 })
      }),
      http.post(`${API}/addresses/${NEW_ADDRESS_ID}/contacts`, async ({ request }) => {
        attached = await request.json()
        return HttpResponse.json(
          { data: { id: '01JQZ0000000000000LINK01' }, meta: [] },
          { status: 201 },
        )
      }),
    )

    render()
    const dialog = await openDialog()

    await userEvent.type(dialog.getByLabelText('Adresse *'), '9 rue du Port')
    await userEvent.type(dialog.getByLabelText('Prénom'), 'Sophie')
    await userEvent.type(dialog.getByLabelText('Téléphone'), '0600000000')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(attached).not.toBeNull())

    // Le contact appartient à l'organisation, pas au carnet du donneur d'ordre.
    expect(contact).toMatchObject({
      firstName: 'Sophie',
      phone: '0600000000',
      entityType: 'organization',
      entityId: ORGANIZATION_ID,
    })
    expect(attached).toMatchObject({ contactId: NEW_CONTACT_ID, isPrimary: true })

    // Et il figure déjà dans les contacts du service : rien à ressaisir.
    await waitFor(() => expect(screen.getByLabelText(/^Prénom/)).toHaveValue('Sophie'))
    expect(screen.getByLabelText('Téléphone')).toHaveValue('0600000000')
  })

  /** Sans contact saisi, l'adresse se crée seule. */
  it('ne crée aucun contact quand le prénom reste vide', async () => {
    serveWizardScope({ catalogEnabled: false })

    let contactCalled = false
    server.use(
      http.post(`${API}/addresses`, () =>
        HttpResponse.json({ data: { id: NEW_ADDRESS_ID }, meta: [] }, { status: 201 }),
      ),
      http.post(`${API}/contacts`, () => {
        contactCalled = true
        return HttpResponse.json({ data: { id: NEW_CONTACT_ID }, meta: [] }, { status: 201 })
      }),
    )

    render()
    const dialog = await openDialog()

    await userEvent.type(dialog.getByLabelText('Adresse *'), '9 rue du Port')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() =>
      expect(screen.queryByRole('button', { name: /Nouvelle adresse/ })).toBeInTheDocument(),
    )
    expect(contactCalled).toBe(false)
  })
})
