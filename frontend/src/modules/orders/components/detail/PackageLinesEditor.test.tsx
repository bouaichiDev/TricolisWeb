import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '../../pages/OrderDetailPage'
import {
  CHILD_PACKAGE_ID,
  LINE_ID,
  makeOrderDetail,
  ORDER_ID,
  PACKAGE_ID,
  PACKAGE_TREE,
} from '../../pages/orderDetailFixtures'

/**
 * La relation colis ↔ ligne, sur une commande déjà enregistrée.
 *
 * `PackageOrderLine` existe au diagramme et l'assistant de création la
 * proposait, mais la fiche affichait lignes et colis côte à côte sans permettre
 * de les relier. C'est ce que ces tests couvrent.
 */
function renderDetail(permissions: string[], detail = makeOrderDetail()) {
  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({ data: detail, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/services/:serviceId/packages`, () =>
      HttpResponse.json({ data: [], meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

/** Ouvre l'onglet Colis puis la fiche de l'un des colis du tableau. */
async function openPackage(index = 0) {
  await userEvent.click(await screen.findByRole('tab', { name: /^Colis/ }))
  await screen.findByText('PAL-1')
  await userEvent.click(screen.getAllByRole('button', { name: 'Contenu du colis' })[index])
}

const openFirstPackage = () => openPackage(0)

describe('contenu d’un colis', () => {
  /**
   * Les trois nombres sont ceux que `PackageLineAllocator` fait respecter sous
   * verrou côté serveur.
   */
  it('affiche commandé, affecté et reste pour chaque ligne', async () => {
    renderDetail(['orders.view'])
    await openFirstPackage()

    // 10 commandés, 4 affectés au colis, 6 restants.
    expect(
      await screen.findByText(/Commandé 10 · Affecté 4 · Reste à affecter 6/),
    ).toBeInTheDocument()
  })

  /** La ligne est déjà dans ce colis : c'est la quantité qui change. */
  it('modifie une quantité déjà affectée', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'packages.update'])

    server.use(
      http.patch(
        `${API}/orders/${ORDER_ID}/packages/${PACKAGE_ID}/lines/${LINE_ID}`,
        async ({ request }) => {
          body = await request.json()
          return HttpResponse.json({ data: {}, meta: [] })
        },
      ),
    )

    await openFirstPackage()

    const quantity = await screen.findByLabelText('Quantité affectée')
    await userEvent.clear(quantity)
    await userEvent.type(quantity, '7')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ orderLineId: LINE_ID, quantity: 7 })
  })

  /** Le colis enfant ne transporte encore rien : l'affectation est une création. */
  it('affecte une ligne à un colis qui n’en portait aucune', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'packages.update'])

    server.use(
      http.post(
        `${API}/orders/${ORDER_ID}/packages/${CHILD_PACKAGE_ID}/lines`,
        async ({ request }) => {
          body = await request.json()
          return HttpResponse.json({ data: {}, meta: [] }, { status: 201 })
        },
      ),
    )

    await openPackage(1)

    await userEvent.type(await screen.findByLabelText('Quantité affectée'), '3')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ orderLineId: LINE_ID, quantity: 3 })
  })

  it('retire une ligne du colis', async () => {
    let called = false
    renderDetail(['orders.view', 'packages.update'])

    server.use(
      http.delete(
        `${API}/orders/${ORDER_ID}/packages/${PACKAGE_ID}/lines/${LINE_ID}`,
        () => {
          called = true
          return new HttpResponse(null, { status: 204 })
        },
      ),
    )

    await openFirstPackage()
    await userEvent.click(await screen.findByRole('button', { name: 'Retirer cette ligne du colis' }))

    await waitFor(() => expect(called).toBe(true))
  })

  /** Le refus du serveur est rédigé pour être lu : il est affiché tel quel. */
  it('affiche le dépassement refusé par le serveur', async () => {
    renderDetail(['orders.view', 'packages.update'])

    server.use(
      http.patch(`${API}/orders/${ORDER_ID}/packages/${PACKAGE_ID}/lines/${LINE_ID}`, () =>
        HttpResponse.json(
          {
            message: 'La quantité affectée dépasse la quantité commandée : 14 demandé pour 10.',
            errors: {},
          },
          { status: 422 },
        ),
      ),
    )

    await openFirstPackage()

    const quantity = await screen.findByLabelText('Quantité affectée')
    await userEvent.clear(quantity)
    await userEvent.type(quantity, '14')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByText(/dépasse la quantité commandée/)).toBeInTheDocument()
  })

  /** Sans la permission, le contenu se lit mais ne se modifie pas. */
  it('reste en lecture seule sans la permission', async () => {
    renderDetail(['orders.view'])
    await openFirstPackage()

    expect(screen.queryByLabelText('Quantité affectée')).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Retirer cette ligne du colis' }),
    ).not.toBeInTheDocument()
  })
})

/**
 * La fiche ne crée pas le contenu d'un colis, elle le corrige.
 *
 * Le lien naît avec la commande — `CreateOrderPackages` lit
 * `packages[].lines[]` — ou, plus tard, du terrain. Ouvrir un panneau ne doit
 * donc **rien** écrire : ce serait affirmer depuis un bureau ce qui se constate
 * dans un entrepôt.
 */
describe('la fiche n’invente pas le contenu', () => {
  // `lines` et `packages` sont optionnels sur `OrderDetail` : la fixture les
  // porte toujours, mais le type ne le sait pas. Les extraire une fois evite
  // d'assener un `!` a chaque usage.
  const base = makeOrderDetail()
  const [firstLine] = base.lines ?? []
  const [firstPackage, secondPackage] = base.packages ?? []

  const soleDetail = () => ({
    ...base,
    lines: [firstLine],
    packages: [{ ...firstPackage, lines: [] }],
  })

  it('n’écrit rien à l’ouverture, même quand une seule réponse est possible', async () => {
    let called = false
    renderDetail(['orders.view', 'packages.update'], soleDetail())

    server.use(
      http.post(`${API}/orders/${ORDER_ID}/packages/:packageId/lines`, () => {
        called = true
        return HttpResponse.json({ data: {}, meta: [] }, { status: 201 })
      }),
    )

    await openPackage()
    await screen.findByText(/Reste à affecter 10/)

    expect(called).toBe(false)
    // Le rattachement reste offert, mais il se demande.
    expect(screen.getByRole('button', { name: /Mettre dans ce colis/ })).toBeInTheDocument()
  })

  /** Deux colis : « tout dans le premier » serait un choix, pas une évidence. */
  it('ne lie rien quand la commande porte plusieurs colis', async () => {
    let called = false
    renderDetail(['orders.view', 'packages.update'], {
      ...base,
      lines: [firstLine],
      packages: [
        { ...firstPackage, lines: [] },
        { ...secondPackage, lines: [] },
      ],
    })

    server.use(
      http.post(`${API}/orders/${ORDER_ID}/packages/:packageId/lines`, () => {
        called = true
        return HttpResponse.json({ data: {}, meta: [] }, { status: 201 })
      }),
    )

    await openPackage()
    await screen.findByText(/Reste à affecter 10/)

    expect(called).toBe(false)
  })

  /** Sans la permission d'écrire, rien ne part — pas même le lien évident. */
  it('ne lie rien sans packages.update', async () => {
    let called = false
    renderDetail(['orders.view'], soleDetail())

    server.use(
      http.post(`${API}/orders/${ORDER_ID}/packages/:packageId/lines`, () => {
        called = true
        return HttpResponse.json({ data: {}, meta: [] }, { status: 201 })
      }),
    )

    await openPackage()
    await screen.findByText(/Reste à affecter 10/)

    expect(called).toBe(false)
  })
})

/**
 * « Quantité affectée » ne se demande que si elle peut varier.
 *
 * Avec un seul colis, une ligne y va tout entière ou n'y va pas : le champ
 * posait une question sans autre réponse que « tout », et personne ne
 * comprenait ce qu'il fallait y mettre.
 */
describe('champ de quantité', () => {
  it('n’est pas montré quand la commande n’a qu’un colis', async () => {
    const base = makeOrderDetail()
    const [firstLine] = base.lines ?? []
    const [firstPackage] = base.packages ?? []

    renderDetail(['orders.view', 'packages.update'], {
      ...base,
      lines: [firstLine],
      packages: [{ ...firstPackage, lines: [{ id: 'l1', orderLineId: LINE_ID, quantity: 10 }] }],
    })

    await openPackage()
    await screen.findByText(/Commandé 10/)

    expect(screen.queryByLabelText('Quantité affectée')).not.toBeInTheDocument()
    expect(screen.getByText(/une ligne y va entièrement, ou pas/)).toBeInTheDocument()
  })

  /** Plusieurs colis : la répartition est réelle, le champ revient. */
  it('revient dès que la commande porte plusieurs colis', async () => {
    renderDetail(['orders.view', 'packages.update'])

    await openPackage()

    expect(await screen.findByLabelText('Quantité affectée')).toBeInTheDocument()
    expect(screen.getByText(/répartie sur plusieurs colis/)).toBeInTheDocument()
  })
})

/**
 * Après un rattachement, la fiche doit montrer le nouveau contenu.
 *
 * Le colis ouvert était gardé en état local, figé au clic : la commande était
 * bien rechargée, mais le tiroir continuait d'afficher l'instantané d'avant —
 * « Affecté 0 » alors que la ligne venait d'y être mise.
 */
describe('rafraîchissement après affectation', () => {
  it('montre la quantité affectée sans rouvrir le tiroir', async () => {
    const base = makeOrderDetail()
    const [firstLine] = base.lines ?? []
    const [firstPackage] = base.packages ?? []

    let linked = false
    // Les autres routes de la fiche, que `renderDetail` pose d'habitude :
    // `onUnhandledRequest: 'error'` impose de toutes les declarer.
    server.use(
      http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
        HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
      ),
      http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
      http.get(`${API}/orders/${ORDER_ID}/services/:serviceId/packages`, () =>
        HttpResponse.json({ data: [], meta: [] }),
      ),
      http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
      http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
    )

    server.use(
      http.get(`${API}/orders/${ORDER_ID}`, () =>
        HttpResponse.json({
          data: {
            ...base,
            lines: [firstLine],
            packages: [
              {
                ...firstPackage,
                lines: linked ? [{ id: 'l1', orderLineId: LINE_ID, quantity: 10 }] : [],
              },
            ],
          },
          meta: [],
        }),
      ),
      http.post(`${API}/orders/${ORDER_ID}/packages/${PACKAGE_ID}/lines`, () => {
        linked = true
        return HttpResponse.json({ data: {}, meta: [] }, { status: 201 })
      }),
    )

    renderWithProviders(<OrderDetailPage />, {
      membership: withPermissions(['orders.view', 'packages.update']),
      route: `/orders/${ORDER_ID}`,
      routePath: '/orders/:id',
    })

    await openPackage()
    await userEvent.click(await screen.findByRole('button', { name: /Mettre dans ce colis/ }))

    expect(await screen.findByText(/Affecté 10 · Reste à affecter 0/)).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: /Mettre dans ce colis/ }),
    ).not.toBeInTheDocument()

    // Cette quantité-là vient de `pkg.lines`, pas des totaux : elle ne
    // s'affiche que si le tiroir relit le colis dans la liste rafraîchie.
    const sheet = within(screen.getByRole('dialog'))
    expect(sheet.getByText('10')).toBeInTheDocument()
    expect(sheet.getByRole('button', { name: 'Retirer cette ligne du colis' })).toBeInTheDocument()
  })
})
