import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { ProviderPortalAccessTab } from './ProviderPortalAccessTab'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const PROVIDER_ID = '01JQZ00000000000000PROVID'
const BASE = `${API}/providers/${PROVIDER_ID}/portal-users`

const roles = [
  { id: '01JQZ0000000000000ROLEADM', code: 'PROVIDER_ADMIN', name: 'Administrateur fournisseur', permissions: [] },
  { id: '01JQZ0000000000000ROLEVWR', code: 'PROVIDER_VIEWER', name: 'Lecture', permissions: [] },
]

function member(id: string, status: string, statusLabel: string, email: string) {
  return {
    id,
    status,
    statusLabel,
    invitedAt: null,
    activatedAt: null,
    disabledAt: null,
    user: { id: `u-${id}`, firstName: 'Alice', lastName: id, email, lastLoginAt: null },
    roles: [{ id: roles[0].id, code: roles[0].code, name: roles[0].name }],
  }
}

const members = [
  member('ACTIVE', 'active', 'Actif', 'active@alpha.test'),
  member('INVITED', 'invited', 'Invité', 'invited@alpha.test'),
  member('DISABLED', 'disabled', 'Désactivé', 'disabled@alpha.test'),
]

function handlers(calls: string[] = [], bodies: unknown[] = []) {
  return [
    http.get(BASE, () => HttpResponse.json({ data: members, meta: {} })),
    http.get(`${API}/providers/${PROVIDER_ID}/portal-roles`, () => {
      calls.push('roles')
      return HttpResponse.json({ data: roles, meta: {} })
    }),
    http.post(`${BASE}/invite`, async ({ request }) => {
      bodies.push(await request.json())
      return HttpResponse.json({ data: members[1], meta: {} }, { status: 201 })
    }),
    http.post(`${BASE}/:member/:action`, ({ params }) => {
      calls.push(`${String(params.action)}:${String(params.member)}`)
      return HttpResponse.json({ data: members[0], meta: {} })
    }),
  ]
}

const MANAGE = withPermissions(['providers.view', 'providers.update'])

describe('ProviderPortalAccessTab', () => {
  it('liste les accès : nom, e-mail, rôle et statut', async () => {
    server.use(...handlers())
    renderWithProviders(<ProviderPortalAccessTab providerId={PROVIDER_ID} active />, { membership: MANAGE })

    const row = (await screen.findByText('active@alpha.test')).closest('tr') as HTMLElement
    expect(within(row).getByText('Alice ACTIVE')).toBeInTheDocument()
    expect(within(row).getByText('Administrateur fournisseur')).toBeInTheDocument()
    expect(within(row).getByText('Actif')).toBeInTheDocument()
  })

  it('invite avec le rôle fournisseur proposé par défaut, lu depuis la fiche du fournisseur', async () => {
    const calls: string[] = []
    const bodies: unknown[] = []
    server.use(...handlers(calls, bodies))
    renderWithProviders(<ProviderPortalAccessTab providerId={PROVIDER_ID} active />, { membership: MANAGE })
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Inviter un compte' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText(/E-mail/), 'nina@alpha.test')
    await user.type(within(dialog).getByLabelText(/Prénom/), 'Nina')
    await user.click(within(dialog).getByRole('button', { name: 'Envoyer l’invitation' }))

    await waitFor(() => expect(bodies).toEqual([{ email: 'nina@alpha.test', firstName: 'Nina', lastName: '', roleId: roles[0].id }]))
    expect(calls).toContain('roles')
  })

  it('propose l’action qui convient à chaque statut', async () => {
    const calls: string[] = []
    server.use(...handlers(calls))
    renderWithProviders(<ProviderPortalAccessTab providerId={PROVIDER_ID} active />, { membership: MANAGE })
    const user = userEvent.setup()

    const rowOf = async (email: string) => (await screen.findByText(email)).closest('tr') as HTMLElement
    expect(within(await rowOf('active@alpha.test')).queryByRole('button', { name: 'Réactiver l’accès' })).not.toBeInTheDocument()
    expect(within(await rowOf('disabled@alpha.test')).queryByRole('button', { name: 'Désactiver l’accès' })).not.toBeInTheDocument()

    await user.click(within(await rowOf('active@alpha.test')).getByRole('button', { name: 'Désactiver l’accès' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Désactiver l’accès' }))
    await user.click(within(await rowOf('disabled@alpha.test')).getByRole('button', { name: 'Réactiver l’accès' }))
    await user.click(within(await rowOf('invited@alpha.test')).getByRole('button', { name: 'Renvoyer l’invitation' }))

    await waitFor(() => expect(calls).toEqual(expect.arrayContaining(['disable:ACTIVE', 'enable:DISABLED', 'resend-invitation:INVITED'])))
  })

  it('permet de désactiver une invitation envoyée par erreur', async () => {
    const calls: string[] = []
    server.use(...handlers(calls))
    renderWithProviders(<ProviderPortalAccessTab providerId={PROVIDER_ID} active />, { membership: MANAGE })
    const user = userEvent.setup()

    const row = (await screen.findByText('invited@alpha.test')).closest('tr') as HTMLElement
    await user.click(within(row).getByRole('button', { name: 'Désactiver l’accès' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Désactiver l’accès' }))

    await waitFor(() => expect(calls).toContain('disable:INVITED'))
  })

  it('dit pourquoi une action est refusée', async () => {
    server.use(
      http.post(`${BASE}/DISABLED/enable`, () =>
        HttpResponse.json({ message: 'Invalide.', errors: { member: ['Ce compte n’a pas encore accepté son invitation : renvoyez-la plutôt.'] } }, { status: 422 }),
      ),
      ...handlers(),
    )
    renderWithProviders(<ProviderPortalAccessTab providerId={PROVIDER_ID} active />, { membership: MANAGE })
    const user = userEvent.setup()

    const row = (await screen.findByText('disabled@alpha.test')).closest('tr') as HTMLElement
    await user.click(within(row).getByRole('button', { name: 'Réactiver l’accès' }))

    expect(await screen.findByText('Ce compte n’a pas encore accepté son invitation : renvoyez-la plutôt.')).toBeInTheDocument()
  })

  it('change le rôle d’un accès', async () => {
    const bodies: unknown[] = []
    server.use(
      ...handlers(),
      http.patch(`${BASE}/ACTIVE`, async ({ request }) => {
        bodies.push(await request.json())
        return HttpResponse.json({ data: members[0], meta: {} })
      }),
    )
    renderWithProviders(<ProviderPortalAccessTab providerId={PROVIDER_ID} active />, { membership: MANAGE })
    const user = userEvent.setup()

    const row = (await screen.findByText('active@alpha.test')).closest('tr') as HTMLElement
    await user.click(within(row).getByRole('button', { name: 'Changer le rôle' }))
    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('combobox'))
    await user.click(await screen.findByRole('option', { name: 'Lecture' }))
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(bodies).toEqual([{ roleId: roles[1].id }]))
  })

  it('n’offre ni invitation ni action sans `providers.update`', async () => {
    server.use(...handlers())
    renderWithProviders(<ProviderPortalAccessTab providerId={PROVIDER_ID} active />, { membership: withPermissions(['providers.view']) })

    expect(await screen.findByText('active@alpha.test')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Inviter un compte' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Désactiver l’accès' })).not.toBeInTheDocument()
  })
})
