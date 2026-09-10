import { expect, request as playwrightRequest } from '@playwright/test'

import { ADMIN } from './support'

const API = process.env.E2E_API_URL ?? 'http://localhost:8001/api/v1'

/**
 * Prépare les données d'un scénario **par l'API**, pas par l'écran.
 *
 * Deux raisons, et la première suffirait :
 *
 * - les semeurs de démonstration ne tournent qu'en environnement `local`. Un
 *   scénario qui en dépendrait se sauterait faute de données, et le §31
 *   interdit le test sauté ;
 * - cliquer douze écrans pour obtenir une facture testerait douze écrans à
 *   chaque exécution. Quand le scénario tomberait, on ne saurait pas lequel a
 *   cédé.
 *
 * Ce qui est vérifié se fait à l'écran ; ce qui n'est qu'un préalable se pose
 * par l'API.
 */
export interface Prepared {
  token: string
  organizationId: string
  customerId: string
  invoiceId: string
  invoiceNumber: string
}

export async function prepareInvoice(): Promise<Prepared> {
  const context = await playwrightRequest.newContext()

  const login = await context.post(`${API}/auth/login`, {
    data: { email: ADMIN.email, password: ADMIN.password, deviceName: 'e2e' },
  })
  expect(login.ok(), 'connexion API').toBeTruthy()

  const token = (await login.json()).data.token as string
  const authed = { Authorization: `Bearer ${token}` }

  // `/auth/me` rend `data.user.organizations` : la ressource nomme ainsi les
  // rattachements, et chacun porte directement l'identifiant de l'organisation.
  const me = await context.get(`${API}/auth/me`, { headers: authed })
  const organizations = (await me.json()).data.user.organizations as Array<{ id: string }>
  expect(organizations?.length, 'le compte de test appartient à une organisation').toBeGreaterThan(0)
  const organizationId = organizations[0].id

  const headers = { ...authed, 'X-Organization-Id': organizationId }
  const stamp = Date.now()

  const customer = await context.post(`${API}/customers`, {
    headers,
    data: { code: `E2E${stamp}`, name: `Client E2E ${stamp}`, status: 'active' },
  })
  expect(customer.ok(), `création client : ${await customer.text()}`).toBeTruthy()
  const customerId = (await customer.json()).data.id as string

  // Une ligne saisie à la main : `orderServiceId` est facultatif, et bâtir une
  // commande livrée pour obtenir une facture ferait dépendre ce scénario de
  // toute la chaîne d'exploitation.
  const invoiceNumber = `INV-E2E-${stamp}`
  const invoice = await context.post(`${API}/invoices`, {
    headers,
    data: {
      customerId,
      invoiceNumber,
      invoiceDate: new Date().toISOString().slice(0, 10),
      currencyCode: 'CHF',
      status: 'draft',
      lines: [
        {
          lineNumber: 1,
          description: 'Prestation E2E',
          quantity: 1,
          unitPrice: 100,
          // `billable` est le seul code semé pour `invoice_line` ; la requête
          // l'exige, et l'omettre revenait en 422.
          status: 'billable',
        },
      ],
    },
  })
  expect(invoice.ok(), `création facture : ${await invoice.text()}`).toBeTruthy()
  const created = (await invoice.json()).data

  await context.dispose()

  return { token, organizationId, customerId, invoiceId: created.id, invoiceNumber }
}

/**
 * Pose un modèle de facture **propre à un client**.
 *
 * Un modèle du transporteur suffirait, mais les scénarios partagent une base :
 * celui qu'un autre aurait créé entre-temps gagnerait ou perdrait selon l'ordre
 * alphabétique de son code, et le test dirait tantôt vrai tantôt faux. Un
 * modèle client, lui, l'emporte toujours pour ce client — et sur personne
 * d'autre.
 */
export async function attachCustomerTemplate(
  prepared: Prepared,
  body: string,
): Promise<{ id: string; code: string }> {
  const context = await playwrightRequest.newContext()
  const headers = {
    Authorization: `Bearer ${prepared.token}`,
    'X-Organization-Id': prepared.organizationId,
  }

  const code = `E2E_TPL_${Date.now()}_${Math.floor(Math.random() * 1000)}`

  const response = await context.post(`${API}/templates`, {
    headers,
    data: {
      customerId: prepared.customerId,
      code,
      name: 'Modèle client E2E',
      templateType: 'invoice',
      bodyFormat: 'html',
      bodyTemplate: body,
      language: 'fr',
      availableVariables: ['invoice.invoiceNumber'],
      isDefault: true,
      isActive: true,
    },
  })
  expect(response.ok(), `création modèle : ${await response.text()}`).toBeTruthy()

  const id = (await response.json()).data.id as string
  await context.dispose()

  return { id, code }
}

/** Modifie le corps d'un modèle, comme un utilisateur le ferait plus tard. */
export async function rewriteTemplate(
  prepared: Prepared,
  templateId: string,
  body: string,
): Promise<void> {
  const context = await playwrightRequest.newContext()

  const response = await context.patch(`${API}/templates/${templateId}`, {
    headers: {
      Authorization: `Bearer ${prepared.token}`,
      'X-Organization-Id': prepared.organizationId,
    },
    data: { bodyTemplate: body },
  })
  expect(response.ok(), `modification modèle : ${await response.text()}`).toBeTruthy()

  await context.dispose()
}

/**
 * Ce qu'un bon de livraison suppose : une commande, un service, un colis, et
 * un article rangé dedans.
 *
 * Le service du **référentiel** est créé pour l'occasion, et sert ensuite à
 * viser le modèle. C'est ce qui rend le scénario déterministe sur une base
 * partagée : un modèle qui désigne une prestation l'emporte sur un modèle
 * générique, quel que soit ce qu'un autre scénario a laissé derrière lui.
 */
export interface PreparedDeliveryNote {
  token: string
  organizationId: string
  customerId: string
  serviceCode: string
  serviceName: string
  orderId: string
  orderNumber: string
  packageReference: string
  articleName: string
}

export async function prepareDeliveryNote(): Promise<PreparedDeliveryNote> {
  const context = await playwrightRequest.newContext()

  const login = await context.post(`${API}/auth/login`, {
    data: { email: ADMIN.email, password: ADMIN.password, deviceName: 'e2e' },
  })
  expect(login.ok(), 'connexion API').toBeTruthy()

  const token = (await login.json()).data.token as string
  const authed = { Authorization: `Bearer ${token}` }

  const me = await context.get(`${API}/auth/me`, { headers: authed })
  const organizations = (await me.json()).data.user.organizations as Array<{ id: string }>
  expect(organizations?.length, 'le compte de test appartient à une organisation').toBeGreaterThan(0)
  const organizationId = organizations[0].id

  const headers = { ...authed, 'X-Organization-Id': organizationId }
  const stamp = Date.now()

  // L'agence est semée en `testing` ; la créer ici en ferait une deuxième à
  // chaque exécution, et le formulaire de commande finirait par en proposer
  // cinquante identiques.
  const agencies = await context.get(`${API}/agencies?perPage=1&status=active`, { headers })
  const agency = (await agencies.json()).data as Array<{ id: string }>
  expect(agency?.length, 'une agence est semée en base de test').toBeGreaterThan(0)

  const customer = await context.post(`${API}/customers`, {
    headers,
    data: { code: `BL${stamp}`, name: `Client BL ${stamp}`, status: 'active' },
  })
  expect(customer.ok(), `création client : ${await customer.text()}`).toBeTruthy()
  const customerId = (await customer.json()).data.id as string

  const address = await context.post(`${API}/addresses`, {
    headers,
    data: {
      name: `Entrepôt BL ${stamp}`,
      addressLine1: '12 route des Acacias',
      postalCode: '1227',
      city: 'Carouge',
      country: 'CH',
      status: 'active',
      entityType: 'customer',
      entityId: customerId,
    },
  })
  expect(address.ok(), `création adresse : ${await address.text()}`).toBeTruthy()
  const addressId = (await address.json()).data.id as string

  const serviceCode = `BLSRV${stamp}`
  const serviceName = `Livraison BL ${stamp}`
  const service = await context.post(`${API}/services`, {
    headers,
    data: {
      code: serviceCode,
      name: serviceName,
      unit: 'delivery',
      defaultDurationMinutes: 30,
      billableToCustomer: true,
      payableToProvider: true,
      requiresAddress: true,
      requiresContact: false,
      status: 'active',
    },
  })
  expect(service.ok(), `création prestation : ${await service.text()}`).toBeTruthy()
  const serviceId = (await service.json()).data.id as string

  const packageReference = `PLT-${stamp}`
  const articleName = `Chaise ${stamp}`

  const order = await context.post(`${API}/orders`, {
    headers,
    data: {
      customerId,
      agencyId: agency[0].id,
      orderDate: new Date().toISOString(),
      customerReference: `BC-${stamp}`,
      lines: [{ name: articleName, articleCode: `ART-${stamp}`, quantity: 10, weight: 6 }],
      packages: [
        {
          key: 'palette-1',
          barcode: `PLT${stamp}`,
          reference: packageReference,
          weight: 60,
          lines: [{ lineKey: '0', quantity: 4 }],
        },
      ],
      services: [
        {
          serviceId,
          addressId,
          serviceNumber: 'SRV-1',
          sequence: 1,
          requestedDate: new Date().toISOString().slice(0, 10),
          quantity: 1,
          unit: 'delivery',
          requiredTimeMinutes: 30,
          remainingTimeMinutes: 30,
          weight: 60,
          volume: 1,
          packageCount: 1,
          customerUnitPrice: 0,
          customerTotalPrice: 0,
          providerUnitCost: 0,
          providerTotalCost: 0,
          status: 'draft',
          packages: [{ packageKey: 'palette-1', quantity: 1 }],
        },
      ],
    },
  })
  expect(order.ok(), `création commande : ${await order.text()}`).toBeTruthy()
  const created = (await order.json()).data

  await context.dispose()

  return {
    token,
    organizationId,
    customerId,
    serviceCode,
    serviceName,
    orderId: created.id as string,
    orderNumber: created.orderNumber as string,
    packageReference,
    articleName,
  }
}
