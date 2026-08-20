import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderCreatePage } from './OrderCreatePage'
import { fillOrder, goTo, pick } from './wizardActions'
import {
  ADDRESS_ID,
  CUSTOMER_ID,
  OTHER_ADDRESS_ID,
  OTHER_CUSTOMER_ID,
  SERVICE_ID,
  serveWizardScope,
} from './wizardScope'

const render = () =>
  renderWithProviders(<OrderCreatePage />, {
    membership: withPermissions(['orders.view', 'orders.create', 'catalogs.view']),
    route: '/orders/create',
  })

describe('OrderCreatePage', () => {
  it('présente cinq étapes, sans étape « Arrêts »', async () => {
    serveWizardScope()
    render()

    for (const step of ['Général', 'Lignes', 'Colis', 'Services', 'Récapitulatif']) {
      expect(
        await screen.findByRole('button', { name: new RegExp(`${step}$`) }),
      ).toBeInTheDocument()
    }

    expect(screen.queryByRole('button', { name: /Arrêts/i })).not.toBeInTheDocument()
  })

  /** Les colis sont `sometimes` côté serveur : l'étape le dit au lieu d'insister. */
  it('annonce que les colis sont facultatifs', async () => {
    serveWizardScope()
    render()

    await goTo('Colis')

    expect(await screen.findByText(/peut être créée sans aucun colis/i)).toBeInTheDocument()
  })

  it('propose le catalogue quand le client l’a activé', async () => {
    serveWizardScope({ catalogEnabled: true })
    render()

    await pick(/^Client/, /Client Alpha/)
    await goTo('Lignes')

    expect(
      await screen.findByRole('button', { name: /Choisir dans le catalogue/ }),
    ).toBeInTheDocument()
  })

  it('explique la saisie libre quand le client n’a pas le catalogue', async () => {
    serveWizardScope({ catalogEnabled: false })
    render()

    await pick(/^Client/, /Client Alpha/)
    await goTo('Lignes')

    expect(await screen.findByText(/n’a pas le catalogue activé/i)).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: /Choisir dans le catalogue/ }),
    ).not.toBeInTheDocument()
  })

  /** Rien ne part avant la dernière étape, et rien ne part si le brouillon est incomplet. */
  it('n’envoie rien quand des champs obligatoires manquent', async () => {
    serveWizardScope()

    const posts: unknown[] = []
    server.use(
      http.post(`${API}/orders`, async ({ request }) => {
        posts.push(await request.json())
        return HttpResponse.json({ data: {}, meta: [] }, { status: 201 })
      }),
    )

    render()
    await goTo('Récapitulatif')
    await userEvent.click(screen.getByRole('button', { name: 'Créer la commande' }))

    expect(posts).toHaveLength(0)
    // Les étapes fautives sont signalées dans le fil, pas seulement en bandeau.
    await waitFor(() => {
      expect(screen.getAllByLabelText('Cette étape contient des erreurs.').length).toBeGreaterThan(
        0,
      )
    })
  })

  it('envoie une commande complète saisie librement', async () => {
    serveWizardScope({ catalogEnabled: false })

    let body: Record<string, never> | null = null
    server.use(
      http.post(`${API}/orders`, async ({ request }) => {
        body = (await request.json()) as Record<string, never>
        return HttpResponse.json({ data: { id: '01JQZ00000000000000ORD01' }, meta: [] }, { status: 201 })
      }),
    )

    render()
    await fillOrder()
    await goTo('Récapitulatif')
    await userEvent.click(screen.getByRole('button', { name: 'Créer la commande' }))

    await waitFor(() => expect(body).not.toBeNull())

    const payload = body as unknown as {
      customerId: string
      agencyId: string
      lines: { name: string; catalogItemId: string | null }[]
      packages: unknown[]
      services: { serviceId: string; addressId: string; customerUnitPrice: number }[]
    }

    expect(payload.customerId).toBe(CUSTOMER_ID)
    expect(payload.lines[0]).toMatchObject({ name: 'Palette de cartons', catalogItemId: null })
    // Aucun colis : le tableau part vide, ce que le serveur accepte.
    expect(payload.packages).toEqual([])
    expect(payload.services[0]).toMatchObject({
      serviceId: SERVICE_ID,
      addressId: ADDRESS_ID,
      customerUnitPrice: 120,
    })
  })

  it('envoie l’article choisi quand la ligne vient du catalogue', async () => {
    serveWizardScope({ catalogEnabled: true })

    let body: unknown = null
    server.use(
      http.post(`${API}/orders`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: { id: '01JQZ00000000000000ORD01' }, meta: [] }, { status: 201 })
      }),
    )

    render()
    await fillOrder({ fromCatalog: true })
    await goTo('Récapitulatif')
    await userEvent.click(screen.getByRole('button', { name: 'Créer la commande' }))

    await waitFor(() => expect(body).not.toBeNull())

    const payload = body as { lines: { catalogItemId: string | null; articleCode: string }[] }

    expect(payload.lines[0].catalogItemId).toBe('01JQZ00000000000000ITEM1')
    expect(payload.lines[0].articleCode).toBe('ART-9')
  })

  /**
   * Une commande porte souvent un chargement chez le donneur d'ordre et une
   * livraison chez le destinataire : deux services, deux clients. L'adresse
   * n'est donc pas bornée au client de la commande.
   */
  it('accepte l’adresse d’un autre client que le donneur d’ordre', async () => {
    serveWizardScope({ catalogEnabled: false })

    let body: unknown = null
    server.use(
      http.post(`${API}/orders`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json(
          { data: { id: '01JQZ00000000000000ORD01' }, meta: [] },
          { status: 201 },
        )
      }),
    )

    render()
    await fillOrder()

    // Le client de la commande est marqué comme donneur d'ordre.
    await pick(/^Client de ce service/, /Destinataire Beta/)
    await pick(/^Adresse \*/, /Chantier Marrakech/)

    await goTo('Récapitulatif')
    await userEvent.click(screen.getByRole('button', { name: 'Créer la commande' }))

    await waitFor(() => expect(body).not.toBeNull())

    const payload = body as { customerId: string; services: { addressId: string }[] }

    // La commande reste au donneur d'ordre ; le service part chez l'autre.
    expect(payload.customerId).toBe(CUSTOMER_ID)
    expect(payload.services[0].addressId).toBe(OTHER_ADDRESS_ID)
    expect(OTHER_CUSTOMER_ID).not.toBe(CUSTOMER_ID)
  })

  /**
   * Le §34 : un 422 imbriqué doit désigner l'étape et le champ, sans effacer
   * ce qui a été saisi.
   */
  it('reporte un 422 imbriqué sur l’étape concernée en conservant la saisie', async () => {
    serveWizardScope({ catalogEnabled: false })

    server.use(
      http.post(`${API}/orders`, () =>
        HttpResponse.json(
          {
            message: 'Les données fournies sont invalides.',
            errors: { 'services.0.serviceNumber': ['Ce numéro est déjà utilisé.'] },
          },
          { status: 422 },
        ),
      ),
    )

    render()
    await fillOrder()
    await goTo('Récapitulatif')
    await userEvent.click(screen.getByRole('button', { name: 'Créer la commande' }))

    expect(await screen.findByText(/Le serveur a refusé la commande/i)).toBeInTheDocument()

    await goTo('Services')

    expect(await screen.findByText('Ce numéro est déjà utilisé.')).toBeInTheDocument()
    // La saisie n'a pas été réinitialisée.
    expect(screen.getByLabelText(/^N° service/)).toHaveValue('SRV-1')
  })
})
