import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { DriverDetailPage } from './DriverDetailPage'

const DRIVER_ID = '01JQZ0000000000000DRIV01'
const MEMBERSHIP_ID = '01JQZ0000000000000MEMB01'
const USER_ID = '01JQZ00000000000000USER1'

const driver = (overrides: Record<string, unknown> = {}) => ({
  id: DRIVER_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  providerId: '01JQZ0000000000000PROV01',
  userId: USER_ID,
  addressId: null,
  contactId: null,
  code: 'CH2',
  name: 'Alex Nalex',
  status: 'active',
  provider: { id: '01JQZ0000000000000PROV01', code: 'TRANS-01', name: 'Transports Atlas' },
  providerName: 'Transports Atlas',
  user: { id: USER_ID, firstName: 'Alex', lastName: 'Nalex', email: 'alex@mail.com' },
  membershipId: MEMBERSHIP_ID,
  ...overrides,
})

function render(overrides: Record<string, unknown> = {}) {
  server.use(
    http.get(`${API}/statuses`, () => HttpResponse.json({ data: [], meta: { total: 0 } })),
    http.get(`${API}/drivers/${DRIVER_ID}`, () =>
      HttpResponse.json({ data: driver(overrides), meta: [] }),
    ),
  )

  renderWithProviders(<DriverDetailPage />, {
    membership: withPermissions(['drivers.view']),
    route: `/drivers/${DRIVER_ID}`,
    routePath: '/drivers/:id',
  })
}

describe('fiche d’un chauffeur', () => {
  /**
   * La fiche d'un membre s'adresse par son **appartenance**, pas par
   * l'utilisateur : `/users/{userId}` répondait « aucun résultat pour le modèle
   * OrganizationUser ».
   */
  it('mène au compte par l’identifiant d’appartenance', async () => {
    render()

    const link = await screen.findByRole('link', { name: /alex@mail\.com/ })
    expect(link).toHaveAttribute('href', `/users/${MEMBERSHIP_ID}`)
  })

  /** Le compte a quitté l'organisation : on le montre, sans lien qui échouerait. */
  it('affiche le compte sans lien quand l’appartenance manque', async () => {
    render({ membershipId: null })

    expect(await screen.findByText(/alex@mail\.com/)).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /alex@mail\.com/ })).not.toBeInTheDocument()
  })

  /** Une case vide se prend pour un chargement qui n'a pas abouti. */
  it('dit qu’un chauffeur du transporteur n’a pas de fournisseur', async () => {
    render({ providerId: null, provider: null, providerName: undefined })

    expect(await screen.findByText(/chauffeur du transporteur/)).toBeInTheDocument()
  })

  it('annonce l’absence de compte', async () => {
    render({ user: null, userId: null, membershipId: null })

    expect(await screen.findByText('Aucun compte rattaché.')).toBeInTheDocument()
  })
})
