import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { InvoiceCreatePage } from './InvoiceCreatePage'

const CUSTOMER_ID = '01JQZ0000000000000CUSTO1'
const OTHER_CUSTOMER_ID = '01JQZ0000000000000CUSTO2'

const customer = (id: string, name: string, code: string) => ({
  id,
  code,
  name,
  status: 'active',
  organizationId: '01JQZ0000000000000000ORG1',
})

const service = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ000000000000OSRV01',
  serviceNumber: 'S-001',
  orderId: '01JQZ00000000000000ORD1',
  orderNumber: 'CMD-100',
  customerReference: 'REF-1',
  currencyCode: 'CHF',
  serviceCode: 'DEL',
  serviceName: 'Livraison',
  requestedDate: '2026-08-12',
  quantity: 2,
  unit: 'palette',
  customerUnitPrice: 50,
  customerTotalPrice: 100,
  weight: 12,
  volume: 0.5,
  packageCount: 2,
  status: 'completed',
  address: {
    id: '01JQZ0000000000000ADDR01',
    code: 'ADR-1',
    name: 'Migros',
    addressLine1: 'Rue du Rhône 12',
    postalCode: '1204',
    city: 'Genève',
    country: 'CH',
  },
  ...overrides,
})

function render(services = [service()], failure: { status: number; body: { message: string; errors: Record<string, string[]> } } | null = null) {
  const billableCalls: URL[] = []
  const suggestionCalls: URL[] = []
  const created: Record<string, unknown>[] = []

  server.use(
    http.get(`${API}/customers`, () =>
      HttpResponse.json(
        paginated([
          customer(CUSTOMER_ID, 'Migros Genève', 'MIG'),
          customer(OTHER_CUSTOMER_ID, 'Coop Lausanne', 'COO'),
        ]),
      ),
    ),
    http.get(`${API}/customers/:id/billable-services/suggestions`, ({ request }) => {
      const url = new URL(request.url)
      suggestionCalls.push(url)

      return HttpResponse.json({
        data:
          url.searchParams.get('field') === 'service'
            ? ['Chargement', 'Livraison']
            : ['ORD-2026-000907', 'ORD-2026-000937'],
      })
    }),
    http.get(`${API}/customers/:id/billable-services`, ({ request }) => {
      const url = new URL(request.url)
      billableCalls.push(url)

      // Le serveur pagine ce qu'il a filtre : sans filtre, plusieurs pages ;
      // filtre, une seule. C'est ce que l'ecran doit croire.
      const filtered = url.searchParams.has('service[]') || url.searchParams.has('order')

      return HttpResponse.json(
        paginated(services, filtered ? {} : { lastPage: 3, total: 12, currentPage: 1 }),
      )
    }),
    http.post(`${API}/invoices`, async ({ request }) => {
      created.push((await request.json()) as Record<string, unknown>)

      if (failure) return HttpResponse.json(failure.body, { status: failure.status })

      return HttpResponse.json({ data: { id: '01JQZ00000000000000INV1' } }, { status: 201 })
    }),
  )

  renderWithProviders(<InvoiceCreatePage />, {
    membership: withPermissions(['invoices.create', 'invoices.view', 'customers.view']),
  })

  return { billableCalls, suggestionCalls, created }
}

/** Choisit un client dans la liste déroulante de l'en-tête. */
/** Retient une prestation par son numéro : la page porte aussi une case
 *  « toute la page », et viser « la » case serait ambigu. */
async function retain(serviceNumber: string) {
  await userEvent.click(
    await screen.findByRole('checkbox', { name: `Retenir la prestation ${serviceNumber}` }),
  )
}

async function chooseCustomer(name: string) {
  await userEvent.click(screen.getAllByRole('combobox')[0])
  await userEvent.click(await screen.findByRole('option', { name: new RegExp(name) }))
}

describe('composition d’une facture', () => {
  /**
   * Les prestations facturables se demandent client par client : la route
   * n'existe que sous un client, et « toutes les prestations facturables »
   * n'aurait pas de sens.
   */
  it('ne demande rien tant qu’aucun client n’est choisi', async () => {
    const { billableCalls } = render()

    expect(await screen.findByText(/se consultent client par client/)).toBeInTheDocument()
    expect(billableCalls).toHaveLength(0)
  })

  it('liste les prestations du client choisi', async () => {
    const { billableCalls } = render()

    await chooseCustomer('Migros')

    expect(await screen.findByText('S-001')).toBeInTheDocument()
    expect(screen.getByText('CMD-100')).toBeInTheDocument()

    await waitFor(() => expect(billableCalls).toHaveLength(1))
    expect(billableCalls[0].pathname).toContain(CUSTOMER_ID)
  })

  /** §42 : la période est un filtre serveur, pas un tri local. */
  it('borne la période côté serveur', async () => {
    const { billableCalls } = render()

    await chooseCustomer('Migros')
    await screen.findByText('S-001')

    await userEvent.type(screen.getByLabelText('Période du'), '2026-08-01')

    await waitFor(() => {
      const last = billableCalls[billableCalls.length - 1]
      expect(last.searchParams.get('periodFrom')).toBe('2026-08-01')
    })
  })

  /**
   * Une facture ne porte qu'un client. Changer d'avis après avoir retenu des
   * prestations produirait un document mêlant deux destinataires.
   */
  it('fige le client dès la première prestation retenue', async () => {
    render()

    await chooseCustomer('Migros')
    await retain('S-001')

    await waitFor(() => expect(screen.getAllByRole('combobox')[0]).toBeDisabled())
  })

  it('envoie les prestations retenues comme lignes de la facture', async () => {
    const { created } = render()

    await chooseCustomer('Migros')
    await retain('S-001')

    await userEvent.type(screen.getByLabelText('Numéro'), 'INV-2026-001')
    await userEvent.click(screen.getByRole('button', { name: 'Créer la facture' }))

    await waitFor(() => expect(created).toHaveLength(1))

    const payload = created[0] as { customerId: string; status: string; lines: unknown[] }

    expect(payload.customerId).toBe(CUSTOMER_ID)
    // Une facture nait au brouillon : c'est le seul etat ou ses lignes se
    // corrigent encore.
    expect(payload.status).toBe('draft')
    expect(payload.lines).toEqual([
      expect.objectContaining({
        orderServiceId: '01JQZ000000000000OSRV01',
        lineNumber: 1,
        quantity: 2,
        unitPrice: 50,
        description: 'Livraison',
        status: 'billable',
      }),
    ])
  })

  /** §8 : une facture porte au moins une ligne — sans quoi rien à clôturer. */
  it('n’autorise pas une facture sans prestation', async () => {
    render()

    await chooseCustomer('Migros')
    await userEvent.type(screen.getByLabelText('Numéro'), 'INV-2026-001')

    expect(screen.getByRole('button', { name: 'Créer la facture' })).toBeDisabled()
  })

  /**
   * Un numéro déjà pris est le refus le plus courant. Sans relais, le bouton se
   * réactive et rien n’explique pourquoi : l’écran aurait l’air cassé.
   */
  it('montre le refus du serveur', async () => {
    render([service()], {
      status: 422,
      body: {
        message: 'Les données fournies sont invalides.',
        errors: { invoiceNumber: ['Ce numéro de facture existe déjà.'] },
      },
    })

    await chooseCustomer('Migros')
    await retain('S-001')

    await userEvent.type(screen.getByLabelText('Numéro'), 'INV-2026-001')
    await userEvent.click(screen.getByRole('button', { name: 'Créer la facture' }))

    expect(await screen.findByText(/existe déjà/)).toBeInTheDocument()
  })

  /**
   * La devise vient du travail. Sans cela, une livraison suisse partirait
   * facturée en dirhams parce que l'écran propose MAD par défaut.
   */
  it('reprend la devise de la prestation retenue', async () => {
    const { created } = render()

    await chooseCustomer('Migros')
    await retain('S-001')

    await userEvent.type(screen.getByLabelText('Numéro'), 'INV-2026-001')
    await userEvent.click(screen.getByRole('button', { name: 'Créer la facture' }))

    await waitFor(() => expect(created).toHaveLength(1))
    expect(created[0].currencyCode).toBe('CHF')
  })

  /** Un document à deux monnaies n'aurait pas de total. */
  it('refuse une prestation d’une autre devise', async () => {
    render([
      service(),
      service({ id: '01JQZ000000000000OSRV02', serviceNumber: 'S-002', currencyCode: 'EUR' }),
    ])

    await chooseCustomer('Migros')

    await retain('S-001')
    await retain('S-002')

    expect(await screen.findByText(/ne porte qu’une devise/)).toBeInTheDocument()
    expect(screen.getByText('1 prestation retenue')).toBeInTheDocument()
  })

  /**
   * Les filtres partent au serveur. Une liste paginée filtrée dans le
   * navigateur ne porterait que sur la page affichée, et le facturier croirait
   * avoir tout vu.
   */
  it('filtre colonne par colonne, côté serveur', async () => {
    const { billableCalls } = render()

    await chooseCustomer('Migros')
    await screen.findByText('S-001')

    await userEvent.type(screen.getByLabelText('Filtrer par n° de commande'), 'CMD-100')

    await waitFor(() => {
      const last = billableCalls[billableCalls.length - 1]
      expect(last.searchParams.get('order')).toBe('CMD-100')
    })

    await userEvent.type(screen.getByLabelText('Filtrer par adresse'), 'Genève')
    await userEvent.type(screen.getByLabelText('Prix minimal'), '100')

    await waitFor(() => {
      const last = billableCalls[billableCalls.length - 1]
      expect(last.searchParams.get('address')).toBe('Genève')
      expect(last.searchParams.get('priceMin')).toBe('100')
    })
  })

  /** Un champ vidé doit disparaître de la requête : `priceMin=` n'est pas un
   *  nombre, et le serveur le refuserait. */
  it('n’envoie pas un filtre vide', async () => {
    const { billableCalls } = render()

    await chooseCustomer('Migros')
    await screen.findByText('S-001')

    await userEvent.type(screen.getByLabelText('Quantité minimale'), '2')
    await waitFor(() =>
      expect(billableCalls[billableCalls.length - 1].searchParams.get('quantityMin')).toBe('2'),
    )

    await userEvent.clear(screen.getByLabelText('Quantité minimale'))

    await waitFor(() =>
      expect(billableCalls[billableCalls.length - 1].searchParams.has('quantityMin')).toBe(false),
    )
  })

  /** Retenir la page entière : composer une facture de vingt-cinq lignes une
   *  case à la fois est un travail de copiste. */
  it('retient et relâche toute la page', async () => {
    render([
      service(),
      service({ id: '01JQZ000000000000OSRV02', serviceNumber: 'S-002' }),
    ])

    await chooseCustomer('Migros')
    await screen.findByText('S-001')

    const all = screen.getByRole('checkbox', {
      name: 'Retenir toutes les prestations de la page',
    })

    await userEvent.click(all)
    expect(await screen.findByText('2 prestations retenues')).toBeInTheDocument()

    await userEvent.click(all)
    expect(await screen.findByText('0 prestation retenue')).toBeInTheDocument()
  })

  /**
   * **Les suggestions viennent du serveur, pas du tableau.** C'est toute leur
   * utilité : un numéro absent des vingt-cinq lignes visibles peut exister
   * trois pages plus loin.
   */
  it('complète le numéro de commande depuis le serveur', async () => {
    const { suggestionCalls, billableCalls } = render()

    await chooseCustomer('Migros')
    await screen.findByText('S-001')

    await userEvent.type(screen.getByLabelText('Filtrer par n° de commande'), '0009')

    await waitFor(() => {
      const last = suggestionCalls[suggestionCalls.length - 1]
      expect(last.searchParams.get('field')).toBe('order')
      expect(last.searchParams.get('term')).toBe('0009')
    })

    // Choisir une suggestion remplit le filtre, et la liste se redemande.
    await userEvent.click(await screen.findByRole('button', { name: 'ORD-2026-000937' }))

    await waitFor(() =>
      expect(billableCalls[billableCalls.length - 1].searchParams.get('order')).toBe(
        'ORD-2026-000937',
      ),
    )
  })

  /** La référence client a sa colonne : glissée sous le numéro de commande,
   *  elle n'avait pas de filtre à elle. */
  it('filtre séparément sur la référence client', async () => {
    const { billableCalls } = render()

    await chooseCustomer('Migros')
    await screen.findByText('S-001')

    await userEvent.type(screen.getByLabelText('Filtrer par référence client'), 'REF-1')

    await waitFor(() => {
      const last = billableCalls[billableCalls.length - 1]
      expect(last.searchParams.get('reference')).toBe('REF-1')
      expect(last.searchParams.has('order')).toBe(false)
    })
  })

  /**
   * **Plusieurs prestations à la fois.** Facturer les livraisons *et* les
   * chargements est une seule question : le serveur cumule les valeurs en
   * « ou », les additionner en « et » ne rendrait jamais rien.
   */
  it('retient plusieurs prestations dans le filtre', async () => {
    const { billableCalls } = render()

    await chooseCustomer('Migros')
    await screen.findByText('S-001')

    await userEvent.click(screen.getByLabelText('Filtrer par prestation'))
    await userEvent.click(await screen.findByRole('button', { name: 'Livraison' }))

    await userEvent.click(screen.getByLabelText('Filtrer par prestation'))
    await userEvent.click(await screen.findByRole('button', { name: 'Chargement' }))

    await waitFor(() => {
      const last = billableCalls[billableCalls.length - 1]
      expect(last.searchParams.getAll('service[]')).toEqual(['Livraison', 'Chargement'])
    })
  })

  /** Une valeur qu'aucune suggestion ne propose reste saisissable : les
   *  propositions accelerent la saisie, elles ne l'enferment pas. */
  it('accepte une valeur tapée puis validée', async () => {
    const { billableCalls } = render()

    await chooseCustomer('Migros')
    await screen.findByText('S-001')

    await userEvent.type(screen.getByLabelText('Filtrer par prestation'), 'Manutention{Enter}')

    await waitFor(() =>
      expect(billableCalls[billableCalls.length - 1].searchParams.getAll('service[]')).toEqual([
        'Manutention',
      ]),
    )
  })

  /** Un jeton se retire d'un clic, sans vider le reste. */
  it('retire une prestation retenue', async () => {
    const { billableCalls } = render()

    await chooseCustomer('Migros')
    await screen.findByText('S-001')

    await userEvent.type(screen.getByLabelText('Filtrer par prestation'), 'Livraison{Enter}')
    await userEvent.type(screen.getByLabelText('Filtrer par prestation'), 'Chargement{Enter}')

    await userEvent.click(await screen.findByRole('button', { name: 'Retirer « Livraison »' }))

    await waitFor(() =>
      expect(billableCalls[billableCalls.length - 1].searchParams.getAll('service[]')).toEqual([
        'Chargement',
      ]),
    )
  })

  /**
   * **Le piège de la pagination.** Filtrer depuis la page 3 laisserait l'écran
   * sur une page que le résultat filtré n'a plus : la table paraîtrait vide
   * alors que des lignes existent.
   */
  it('revient à la première page quand un filtre change', async () => {
    const { billableCalls } = render()

    await chooseCustomer('Migros')
    await screen.findByText('S-001')

    await userEvent.click(screen.getByRole('button', { name: 'Suivant' }))

    await waitFor(() =>
      expect(billableCalls[billableCalls.length - 1].searchParams.get('page')).toBe('2'),
    )

    await userEvent.type(screen.getByLabelText('Filtrer par prestation'), 'Livraison{Enter}')

    await waitFor(() => {
      const last = billableCalls[billableCalls.length - 1]
      expect(last.searchParams.getAll('service[]')).toEqual(['Livraison'])
      expect(last.searchParams.get('page')).toBe('1')
    })
  })
})
