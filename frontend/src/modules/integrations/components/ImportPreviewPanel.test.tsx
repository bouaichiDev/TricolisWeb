import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { ImportPreviewPanel } from './ImportPreviewPanel'
import { IMPORT_CONFIG_ID } from '../testSupport'
import type { ImportPreview } from '../types/customerIntegration'

const preview = (overrides: Partial<ImportPreview> = {}): ImportPreview => ({
  rowCount: 3,
  columns: ['ART', 'QTE', 'COLIS'],
  payload: { lines: [{ articleCode: 'A-1', quantity: '2' }] },
  errors: {},
  resolvedElsewhere: ['customerId', 'agencyId', 'depotId'],
  ...overrides,
})

function serve(result: ImportPreview) {
  const calls: number[] = []

  server.use(
    http.post(`${API}/customer-import-configurations/${IMPORT_CONFIG_ID}/preview`, async ({ request }) => {
      // Le corps est bien un envoi de fichier, pas du JSON : c'est ce que le
      // serveur attend, et l'affirmer ici suffit — la forme exacte d'un `File`
      // reconstruit par l'interception varie selon l'environnement.
      const form = await request.formData()
      calls.push(form.has('file') ? 1 : 0)

      return HttpResponse.json({ data: result, meta: [] })
    }),
  )

  return calls
}

const render = (hasMapping = true) =>
  renderWithProviders(
    <ImportPreviewPanel configurationId={IMPORT_CONFIG_ID} hasMapping={hasMapping} />,
    { membership: withPermissions(['customer_import_configurations.view']) },
  )

const upload = async (name = 'echantillon.csv') => {
  const input = document.querySelector('input[type="file"]') as HTMLInputElement

  await userEvent.upload(input, new File(['ART,QTE\nA-1,2\n'], name, { type: 'text/csv' }))
}

describe('essai d’une correspondance', () => {
  /** Sans correspondance, il n'y a rien à éprouver : le bouton le dit. */
  it('reste inerte tant qu’aucune correspondance n’est saisie', () => {
    render(false)

    expect(screen.getByRole('button', { name: /Choisir un fichier/ })).toBeDisabled()
    expect(screen.getByText(/Renseignez d’abord une correspondance/)).toBeInTheDocument()
  })

  it('envoie le fichier choisi et le nomme à l’écran', async () => {
    const calls = serve(preview())
    render()

    await upload()

    await waitFor(() => expect(calls).toEqual([1]))
    expect(screen.getByText('echantillon.csv')).toBeInTheDocument()
  })

  /**
   * Les colonnes lues sont ce qui permet de repérer un nom mal orthographié
   * dans la correspondance, avant d'aller chercher ailleurs.
   */
  it('nomme les colonnes trouvées dans le fichier', async () => {
    serve(preview())
    render()

    await upload()

    expect(await screen.findByText('ART')).toBeInTheDocument()
    expect(screen.getByText('COLIS')).toBeInTheDocument()
    expect(screen.getByText('3 lignes lues')).toBeInTheDocument()
  })

  it('montre la charge utile construite', async () => {
    serve(preview())
    render()

    await upload()

    expect(await screen.findByText(/"articleCode": "A-1"/)).toBeInTheDocument()
  })

  /**
   * « Correspondance valide » se lit trop facilement comme « commande créée ».
   * Le verdict porte donc son démenti, et la mention est répétée sous le
   * résultat — l'avertissement d'en-tête est déjà loin quand on arrive là.
   */
  it('annonce une correspondance valide sans laisser croire à un import', async () => {
    serve(preview())
    render()

    await upload()

    expect(await screen.findByText(/aucune commande n’a été créée/)).toBeInTheDocument()
    expect(screen.getByText(/lu en mémoire puis oublié/)).toBeInTheDocument()
  })

  /** Le verdict porte sur les règles réelles de création d'une commande. */
  it('détaille ce qui manquerait', async () => {
    serve(
      preview({
        errors: {
          orderDate: ['Le champ orderDate est obligatoire.'],
          'lines.0.quantity': ['Le champ quantity est obligatoire.'],
        },
      }),
    )
    render()

    await upload()

    expect(await screen.findByText(/2 champs manqueraient/)).toBeInTheDocument()
    expect(screen.getByText('orderDate')).toBeInTheDocument()
    expect(screen.getByText('lines.0.quantity')).toBeInTheDocument()
  })

  /**
   * Ces identifiants ne viennent pas du fichier : sans cette mention, leur
   * absence du verdict passerait pour un oubli.
   */
  it('explique pourquoi les identifiants ne sont pas exigés', async () => {
    serve(preview())
    render()

    await upload()

    expect(await screen.findByText(/ne viennent pas du fichier/)).toBeInTheDocument()
  })

  /** Le message du serveur dit ce qui cloche dans le fichier. */
  it('affiche le refus du serveur tel quel', async () => {
    server.use(
      http.post(`${API}/customer-import-configurations/${IMPORT_CONFIG_ID}/preview`, () =>
        HttpResponse.json({ message: 'Ce fichier CSV ne porte aucune ligne d’en-tête.' }, { status: 422 }),
      ),
    )
    render()

    await upload()

    expect(await screen.findByText(/aucune ligne d’en-tête/)).toBeInTheDocument()
  })
})
