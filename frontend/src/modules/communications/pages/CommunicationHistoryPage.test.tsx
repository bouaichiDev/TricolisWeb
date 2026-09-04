import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { CommunicationHistoryPage } from './CommunicationHistoryPage'

const communication = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ0000000000000COMM001',
  organizationId: '01JQZ0000000000000000ORG1',
  orderId: '01JQZ00000000000000ORD01',
  orderNumber: 'CMD-2026-0042',
  templateId: null,
  communicationRuleId: null,
  channel: 'email',
  communicationType: 'custom',
  recipientRole: 'customer',
  recipientName: 'Marie Dupont',
  recipientEmail: 'marie@example.test',
  recipientPhone: null,
  subject: 'Votre livraison',
  body: 'Bonjour Marie.',
  status: 'sent',
  scheduledAt: null,
  sentAt: '2026-08-02T10:00:00+00:00',
  createdBy: '01JQZ0000000000000USER001',
  createdAt: '2026-08-02T09:00:00+00:00',
  updatedAt: '2026-08-02T10:00:00+00:00',
  ...overrides,
})

function render(rows: unknown[] = [communication()]) {
  const seen: string[] = []

  server.use(
    http.get(`${API}/order-communications`, ({ request }) => {
      seen.push(new URL(request.url).search)

      return HttpResponse.json(paginated(rows))
    }),
  )

  renderWithProviders(<CommunicationHistoryPage />, {
    membership: withPermissions(['order_communications.view']),
  })

  return seen
}

describe('historique des communications', () => {
  it('liste ce qui est parti, avec sa commande et son destinataire', async () => {
    render()

    expect(await screen.findByText('CMD-2026-0042')).toBeInTheDocument()
    expect(screen.getByText('Marie Dupont')).toBeInTheDocument()
    expect(screen.getByText('marie@example.test')).toBeInTheDocument()
    expect(screen.getByText('Envoyée')).toBeInTheDocument()
  })

  /**
   * Aucun champ `origin` n'existe : l'origine se lit de la présence d'une
   * règle, et le §75 interdit d'en inventer un autre.
   */
  it('distingue un message manuel d’un message produit par une règle', async () => {
    render([
      communication(),
      communication({ id: '01JQZ0000000000000COMM002', communicationRuleId: '01JQZ0000000000000RULE01' }),
    ])

    expect(await screen.findByText('Manuelle')).toBeInTheDocument()
    expect(screen.getByText('Règle')).toBeInTheDocument()
  })

  /**
   * Les vues « programmées » et « échecs » sont le même historique filtré, pas
   * des tables séparées — le §86 l'interdit.
   */
  it('filtre par statut au serveur plutôt qu’en mémoire', async () => {
    const seen = render()

    await screen.findByText('CMD-2026-0042')

    await userEvent.click(screen.getByRole('tab', { name: 'Échecs' }))

    await waitFor(() => expect(seen.some((query) => query.includes('status=failed'))).toBe(true))
  })

  it('ouvre le détail sans recalculer le contenu envoyé', async () => {
    render()

    server.use(
      http.get(`${API}/order-communications/01JQZ0000000000000COMM001`, () =>
        HttpResponse.json({ data: communication(), meta: [] }),
      ),
      http.get(`${API}/order-communications/01JQZ0000000000000COMM001/attachments`, () =>
        HttpResponse.json({ data: [], meta: [] }),
      ),
    )

    await userEvent.click(await screen.findByText('CMD-2026-0042'))

    expect(await screen.findByText('Bonjour Marie.')).toBeInTheDocument()
  })
})
