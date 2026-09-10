import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { DeliveryNoteDialog } from './DeliveryNoteDialog'
import type { OrderService } from '../../types/orderDetail'

const ORDER_ID = '01JQZ00000000000000ORD01'
const SERVICE_ID = '01JQZ00000000000000SRV01'
const TEMPLATE_ID = '01JQZ0000000000000TMPL01'
const BASE = `${API}/orders/${ORDER_ID}/services/${SERVICE_ID}/delivery-note`

const service = {
  id: SERVICE_ID,
  orderId: ORDER_ID,
  serviceId: '01JQZ00000000000000CAT01',
  addressId: null,
  serviceNumber: 'SRV-1',
  sequence: 1,
  operational: {
    requestedDate: null,
    requestedFrom: null,
    requestedTo: null,
    quantity: 1,
    unit: 'delivery',
    requiredTimeMinutes: null,
    remainingTimeMinutes: null,
    weight: null,
    volume: null,
    packageCount: null,
    instructions: null,
  },
  billing: { customerUnitPrice: null, customerTotalPrice: null },
  providerCost: { providerUnitCost: null, providerTotalCost: null },
  status: 'draft',
  service: { id: '01JQZ00000000000000CAT01', code: 'LIV', name: 'Livraison' },
} as unknown as OrderService

const option = (overrides: Record<string, unknown> = {}) => ({
  id: TEMPLATE_ID,
  code: 'BL_DEFAULT',
  name: 'BL standard',
  scope: 'global',
  customerId: null,
  serviceId: null,
  language: 'fr',
  isDefault: true,
  ...overrides,
})

function render(permissions: string[], templates: unknown[]) {
  server.use(
    http.get(`${BASE}/templates`, () =>
      HttpResponse.json({
        data: templates,
        meta: { defaultTemplateId: templates.length > 0 ? TEMPLATE_ID : null },
      }),
    ),
    http.get(BASE, () =>
      HttpResponse.json({
        data: {
          html: '<h1>BL-ORD-1-SRV-1</h1>',
          number: 'BL-ORD-1-SRV-1',
          templateId: TEMPLATE_ID,
          templateCode: 'BL_DEFAULT',
          templateName: 'BL standard',
          scope: 'global',
        },
        meta: [],
      }),
    ),
  )

  return renderWithProviders(
    <DeliveryNoteDialog orderId={ORDER_ID} service={service} onClose={() => {}} />,
    { membership: withPermissions(permissions) },
  )
}

describe('bon de livraison', () => {
  it('affiche le document rendu par le serveur, avec la portée du modèle', async () => {
    render(['delivery_notes.view'], [option()])

    expect(await screen.findByText('BL-ORD-1-SRV-1')).toBeInTheDocument()
    expect(screen.getAllByText('Modèle du transporteur').length).toBeGreaterThan(0)

    // Le HTML vient du serveur et s'affiche dans une iframe cloisonnee : le
    // reconstruire en JavaScript montrerait autre chose que le PDF remis.
    const frame = await screen.findByTitle('Bon de livraison')
    expect(frame).toHaveAttribute('srcdoc', '<h1>BL-ORD-1-SRV-1</h1>')

    // `allow-scripts` doit rester absent : un `<script>` glisse dans un modele
    // s'executerait sinon avec la session de qui ouvre l'apercu. Les deux
    // autorisations presentes ne servent qu'a rendre l'impression possible.
    expect(frame).toHaveAttribute('sandbox', 'allow-same-origin allow-modals')
  })

  /**
   * Le cas que la demande nomme explicitement : sans modèle, on ne génère pas,
   * on dit ce qui manque et on mène là où le corriger.
   */
  it('renvoie vers « Modèles » quand aucun modèle ne s’applique', async () => {
    render(['delivery_notes.view', 'templates.view'], [])

    expect(
      await screen.findByText(/Aucun modèle de bon de livraison ne s’applique/),
    ).toBeInTheDocument()

    expect(screen.getByRole('link', { name: /Modèles/ })).toHaveAttribute(
      'href',
      '/templates?category=delivery_note',
    )

    expect(screen.queryByRole('button', { name: /Générer/ })).not.toBeInTheDocument()
  })

  /** Sans le droit d'y aller, le lien ne sert qu'à faire cliquer dans le vide. */
  it('tait le lien vers « Modèles » sans le droit de les voir', async () => {
    render(['delivery_notes.view'], [])

    await screen.findByText(/Aucun modèle de bon de livraison ne s’applique/)

    expect(screen.queryByRole('link', { name: /Modèles/ })).not.toBeInTheDocument()
  })

  it('génère le PDF avec le modèle affiché', async () => {
    let body: unknown = null

    render(['delivery_notes.view', 'delivery_notes.generate'], [option()])

    server.use(
      http.post(BASE, async ({ request }) => {
        body = await request.json()

        return HttpResponse.json(
          { data: { id: '01JQZ000000000000000DOC1', fileName: 'BL.pdf' }, meta: [] },
          { status: 201 },
        )
      }),
      http.get(`${API}/documents/01JQZ000000000000000DOC1/download`, () =>
        HttpResponse.text('%PDF-'),
      ),
    )

    await userEvent.click(await screen.findByRole('button', { name: /Générer/ }))

    await waitFor(() => expect(body).toEqual({ templateId: TEMPLATE_ID }))
  })

  /** Générer engage : c'est un droit distinct de celui de regarder. */
  it('tait le bouton de génération sans le droit correspondant', async () => {
    render(['delivery_notes.view'], [option()])

    await screen.findByText('BL-ORD-1-SRV-1')

    expect(screen.queryByRole('button', { name: /Générer/ })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Imprimer/ })).toBeInTheDocument()
  })
})
