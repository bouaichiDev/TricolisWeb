import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'

import { RoleDashboardPanel } from './RoleDashboardPanel'
import type { RoleDashboardWidget } from '../types/dashboard'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const ROLE_ID = '01JQZ00000000000000ROLE1'

function widget(overrides: Partial<RoleDashboardWidget> = {}): RoleDashboardWidget {
  return {
    key: 'orders_today',
    labelKey: 'dashboardWidgets.orders_today.label',
    descriptionKey: 'dashboardWidgets.orders_today.description',
    category: 'operations',
    type: 'kpi',
    size: 'small',
    requiredPermission: 'orders.view',
    defaultPosition: 0,
    position: 0,
    isEnabled: false,
    availableForRole: true,
    ...overrides,
  }
}

const invoices = widget({
  key: 'draft_invoices',
  labelKey: 'dashboardWidgets.draft_invoices.label',
  descriptionKey: 'dashboardWidgets.draft_invoices.description',
  category: 'billing',
  requiredPermission: 'invoices.view',
  availableForRole: false,
})

function catalogueHandler(widgets: RoleDashboardWidget[]) {
  return http.get(`${API}/roles/${ROLE_ID}/dashboard`, () =>
    HttpResponse.json({ data: widgets, meta: [] }),
  )
}

function renderPanel(widgets: RoleDashboardWidget[], editable = true) {
  server.use(catalogueHandler(widgets))
  renderWithProviders(<RoleDashboardPanel roleId={ROLE_ID} editable={editable} />)
}

describe('réglage du tableau de bord d’un rôle', () => {
  it('groupe les widgets par métier', async () => {
    renderPanel([widget(), invoices])

    expect(await screen.findByText('Exploitation')).toBeInTheDocument()
    expect(screen.getByText('Facturation')).toBeInTheDocument()
  })

  /**
   * Le §17 : l'interrupteur est désactivé et la permission manquante affichée.
   * Ni masqué — on croirait le widget inexistant — ni accordé : cet écran ne
   * distribue aucun droit.
   */
  it('désactive le widget dont le rôle n’a pas la permission, et la nomme', async () => {
    renderPanel([widget(), invoices])

    expect(await screen.findByText('Permission requise : invoices.view')).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Factures en brouillon' })).toBeDisabled()
  })

  it('n’enregistre rien tant qu’on n’a pas cliqué', async () => {
    const user = userEvent.setup()
    renderPanel([widget()])

    await user.click(await screen.findByRole('switch', { name: 'Commandes du jour' }))

    // Aucun gestionnaire `PUT` n'est déclaré : le serveur de test échouerait
    // sur un appel imprévu. Le test tient donc par ce qu'il ne provoque pas.
    expect(screen.getByRole('button', { name: 'Enregistrer la configuration' })).toBeEnabled()
  })

  it('envoie les clés cochées, renumérotées dans l’ordre affiché', async () => {
    const user = userEvent.setup()
    const sent = vi.fn()

    renderPanel([widget(), widget({ key: 'tours_today', labelKey: 'dashboardWidgets.tours_today.label', descriptionKey: 'dashboardWidgets.tours_today.description', category: 'planning', requiredPermission: 'tours.view' })])

    server.use(
      http.put(`${API}/roles/${ROLE_ID}/dashboard`, async ({ request }) => {
        sent(await request.json())

        return HttpResponse.json({ data: [], meta: [] })
      }),
    )

    await user.click(await screen.findByRole('switch', { name: 'Tournées du jour' }))
    await user.click(screen.getByRole('switch', { name: 'Commandes du jour' }))
    await user.click(screen.getByRole('button', { name: 'Enregistrer la configuration' }))

    await waitFor(() =>
      expect(sent).toHaveBeenCalledWith({
        widgets: [
          { key: 'tours_today', position: 1 },
          { key: 'orders_today', position: 2 },
        ],
      }),
    )
  })

  it('remonte un widget actif au clavier', async () => {
    const user = userEvent.setup()
    const sent = vi.fn()

    renderPanel([
      widget({ isEnabled: true, position: 1 }),
      widget({
        key: 'tours_today',
        labelKey: 'dashboardWidgets.tours_today.label',
        descriptionKey: 'dashboardWidgets.tours_today.description',
        category: 'planning',
        requiredPermission: 'tours.view',
        isEnabled: true,
        position: 2,
      }),
    ])

    server.use(
      http.put(`${API}/roles/${ROLE_ID}/dashboard`, async ({ request }) => {
        sent(await request.json())

        return HttpResponse.json({ data: [], meta: [] })
      }),
    )

    const up = await screen.findAllByRole('button', { name: 'Monter' })
    await user.click(up[1])
    await user.click(screen.getByRole('button', { name: 'Enregistrer la configuration' }))

    await waitFor(() =>
      expect(sent).toHaveBeenCalledWith({
        widgets: [
          { key: 'tours_today', position: 1 },
          { key: 'orders_today', position: 2 },
        ],
      }),
    )
  })

  it('réinitialise après confirmation', async () => {
    const user = userEvent.setup()
    const deleted = vi.fn()

    renderPanel([widget({ isEnabled: true })])

    server.use(
      http.delete(`${API}/roles/${ROLE_ID}/dashboard`, () => {
        deleted()

        return HttpResponse.json({ data: [], meta: [] })
      }),
    )

    await user.click(await screen.findByRole('button', { name: 'Réinitialiser' }))

    // Deux boutons portent alors ce nom : celui du panneau et celui de la
    // boîte de confirmation. Le second est le dernier rendu.
    const buttons = await screen.findAllByRole('button', { name: 'Réinitialiser' })
    await user.click(buttons[buttons.length - 1])

    await waitFor(() => expect(deleted).toHaveBeenCalled())
  })

  /**
   * Un rôle de portée plateforme se consulte : il n'appartient pas à
   * l'organisation. Le catalogue reste lisible — c'est ce que ses porteurs
   * verront — mais aucune action n'est proposée.
   */
  it('ne propose aucune action sur un rôle qu’on ne peut pas régler', async () => {
    renderPanel([widget()], false)

    expect(await screen.findByText('Exploitation')).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Enregistrer la configuration' }),
    ).not.toBeInTheDocument()
  })
})
