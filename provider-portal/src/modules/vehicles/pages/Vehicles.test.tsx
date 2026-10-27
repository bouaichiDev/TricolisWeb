import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

const vehicle = {
  id: '01VEH1', code: 'VA-01', registrationNumber: 'GE-1', vehicleType: { id: '01TYPE', name: 'Fourgon' },
  payloadCapacity: '1200.000', volumeCapacity: '12.5000', palletCapacity: 4, status: 'inactive', statusLabel: 'Inactif',
  actions: { canUpdate: true, canDeactivate: false, canActivate: true },
}
const page = (rows: unknown[]) => ({ data: rows, meta: { currentPage: 1, perPage: 25, total: rows.length, lastPage: 1 } })
const FLEET = [...ADMIN_PERMISSIONS, 'provider.vehicles.view', 'provider.vehicles.create', 'provider.vehicles.update']

describe('véhicules', () => {
  beforeEach(() => {
    server.use(http.get(`${API}/vehicle-types`, () => HttpResponse.json({ data: [{ id: '01TYPE', code: 'van', name: 'Fourgon' }], meta: {} })))
  })

  it('ajoute un véhicule avec un type de l’organisation et des capacités numériques', async () => {
    let body: unknown = null
    server.use(
      http.get(`${API}/vehicles`, () => HttpResponse.json(page([]))),
      http.post(`${API}/vehicles`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: vehicle, meta: {} }, { status: 201 })
      }),
    )
    renderApp('/vehicles', makeContext(FLEET))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Ajouter un véhicule' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Code'), 'VA-02')
    await user.type(within(dialog).getByLabelText('Immatriculation'), 'GE-2')
    await user.selectOptions(within(dialog).getByLabelText('Type de véhicule'), await within(dialog).findByRole('option', { name: 'Fourgon' }))
    await user.type(within(dialog).getByLabelText('Charge utile (kg)'), '1200')
    await user.type(within(dialog).getByLabelText('Volume (m³)'), '12.5')
    await user.type(within(dialog).getByLabelText('Palettes'), '4')
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() =>
      expect(body).toEqual({ code: 'VA-02', registrationNumber: 'GE-2', vehicleTypeId: '01TYPE', payloadCapacity: 1200, volumeCapacity: 12.5, palletCapacity: 4 }),
    )
  })

  it('réactive un véhicule inactif', async () => {
    let called = false
    server.use(
      http.get(`${API}/vehicles`, () => HttpResponse.json(page([vehicle]))),
      http.post(`${API}/vehicles/01VEH1/activate`, () => {
        called = true
        return HttpResponse.json({ data: { ...vehicle, status: 'active' }, meta: {} })
      }),
    )
    renderApp('/vehicles', makeContext(FLEET))

    const row = (await screen.findByText('VA-01')).closest('tr') as HTMLElement
    await userEvent.setup().click(within(row).getByRole('button', { name: 'Réactiver' }))

    await waitFor(() => expect(called).toBe(true))
  })
})
