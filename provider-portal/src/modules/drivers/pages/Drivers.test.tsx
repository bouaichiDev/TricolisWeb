import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

const actions = { canUpdate: true, canDeactivate: true, canActivate: false }
const driver = {
  id: '01DRV1', code: 'ALPHA-01', name: 'Ali Alpha', firstName: 'Ali', lastName: 'Alpha', email: 'ali@alpha.test',
  phone: null, status: 'active', statusLabel: 'Actif', actions,
}
const page = (rows: unknown[]) => ({ data: rows, meta: { currentPage: 1, perPage: 25, total: rows.length, lastPage: 1 } })
const FLEET = [...ADMIN_PERMISSIONS, 'provider.drivers.view', 'provider.drivers.create', 'provider.drivers.update']

describe('chauffeurs', () => {
  it('liste ses chauffeurs et cherche côté serveur', async () => {
    const searches: (string | null)[] = []
    server.use(
      http.get(`${API}/drivers`, ({ request }) => {
        searches.push(new URL(request.url).searchParams.get('search'))
        return HttpResponse.json(page([driver]))
      }),
    )
    renderApp('/drivers', makeContext(FLEET))

    expect(await screen.findByText('ALPHA-01')).toBeInTheDocument()
    await userEvent.setup().type(screen.getByRole('textbox', { name: 'Rechercher un chauffeur' }), 'Ali')
    await waitFor(() => expect(searches).toContain('Ali'))
  })

  it('ajoute un chauffeur sans jamais envoyer de fournisseur', async () => {
    let body: unknown = null
    server.use(
      http.get(`${API}/drivers`, () => HttpResponse.json(page([]))),
      http.post(`${API}/drivers`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: driver, meta: {} }, { status: 201 })
      }),
    )
    renderApp('/drivers', makeContext(FLEET))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Ajouter un chauffeur' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Code'), 'ALPHA-02')
    await user.type(within(dialog).getByLabelText('Prénom'), 'Nadia')
    await user.type(within(dialog).getByLabelText('Nom'), 'Neuve')
    await user.type(within(dialog).getByLabelText('E-mail'), 'nadia@alpha.test')
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).toEqual({ code: 'ALPHA-02', firstName: 'Nadia', lastName: 'Neuve', email: 'nadia@alpha.test', phone: null }))

    // Rouvert après un ajout réussi, le formulaire repart vide.
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    await user.click(screen.getByRole('button', { name: 'Ajouter un chauffeur' }))
    expect(within(await screen.findByRole('dialog')).getByLabelText('Code')).toHaveValue('')
  })

  it('affiche le refus neutre du serveur sous le champ', async () => {
    server.use(
      http.get(`${API}/drivers`, () => HttpResponse.json(page([]))),
      http.post(`${API}/drivers`, () =>
        HttpResponse.json({ message: 'Invalide.', errors: { email: ['Cette adresse e-mail ne peut pas être utilisée.'] } }, { status: 422 }),
      ),
    )
    renderApp('/drivers', makeContext(FLEET))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Ajouter un chauffeur' }))
    const dialog = await screen.findByRole('dialog')
    for (const [label, value] of [['Code', 'X1'], ['Prénom', 'A'], ['Nom', 'B'], ['E-mail', 'pris@x.test']]) {
      await user.type(within(dialog).getByLabelText(label), value)
    }
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    expect(await within(dialog).findByText('Cette adresse e-mail ne peut pas être utilisée.')).toBeInTheDocument()
  })

  it('désactive un chauffeur quand le serveur le permet', async () => {
    let called = false
    server.use(
      http.get(`${API}/drivers`, () => HttpResponse.json(page([driver]))),
      http.post(`${API}/drivers/01DRV1/deactivate`, () => {
        called = true
        return HttpResponse.json({ data: { ...driver, status: 'inactive' }, meta: {} })
      }),
    )
    renderApp('/drivers', makeContext(FLEET))
    const user = userEvent.setup()

    const row = (await screen.findByText('ALPHA-01')).closest('tr') as HTMLElement
    expect(within(row).queryByRole('button', { name: 'Réactiver' })).not.toBeInTheDocument()
    await user.click(within(row).getByRole('button', { name: 'Désactiver' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Désactiver' }))

    await waitFor(() => expect(called).toBe(true))
  })

  it('n’offre ni ajout ni action d’écriture à un compte en lecture', async () => {
    server.use(http.get(`${API}/drivers`, () => HttpResponse.json(page([{ ...driver, actions: { canUpdate: false, canDeactivate: false, canActivate: false } }]))))
    renderApp('/drivers', makeContext([...ADMIN_PERMISSIONS, 'provider.drivers.view']))

    expect(await screen.findByText('ALPHA-01')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Ajouter un chauffeur' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Désactiver' })).not.toBeInTheDocument()
  })
})
