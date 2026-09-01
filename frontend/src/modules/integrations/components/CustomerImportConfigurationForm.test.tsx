import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'

import { CustomerImportConfigurationForm } from './CustomerImportConfigurationForm'
import { CUSTOMER_ID, serveCustomers } from '../testSupport'
import type { CustomerImportConfigurationPayload } from '../types/customerIntegration'

type Submit = (payload: CustomerImportConfigurationPayload) => Promise<void>

function render(onSubmit: Submit) {
  serveCustomers()
  renderWithProviders(
    <CustomerImportConfigurationForm
      onSubmit={onSubmit}
      onCancel={() => {}}
      submitLabel="Créer"
    />,
    { membership: withPermissions(['customer_import_configurations.create']) },
  )
}

async function fillRequired() {
  await userEvent.click(await screen.findByLabelText(/^Client/))
  await userEvent.click(await screen.findByRole('option', { name: /Client Nord/ }))
  await userEvent.type(screen.getByLabelText(/^Nom/), 'Commandes ERP')
  await userEvent.type(screen.getByLabelText(/^Type de source/), 'sftp')
  await userEvent.type(screen.getByLabelText(/^Format de fichier/), 'csv')
}

describe('formulaire de configuration d’import', () => {
  /**
   * Le §5 : la configuration décrit une lecture, elle n'en déclenche aucune.
   * Aucun bouton ne doit laisser croire le contraire.
   */
  it('ne propose aucune exécution d’import', async () => {
    render(async () => {})

    await screen.findByLabelText(/^Nom/)

    for (const label of [/Lancer/, /Importer/, /Exécuter/, /Tester/]) {
      expect(screen.queryByRole('button', { name: label })).not.toBeInTheDocument()
    }
  })

  it('envoie les objets JSON tels que saisis', async () => {
    const onSubmit = vi.fn<Submit>(async () => {})
    render(onSubmit)

    await fillRequired()
    await userEvent.type(
      screen.getByLabelText(/^Correspondance des champs/),
      '{{"ref": "REF"}',
    )

    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))
    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))

    expect(onSubmit.mock.calls[0][0]).toEqual({
      customerId: CUSTOMER_ID,
      name: 'Commandes ERP',
      sourceType: 'sftp',
      fileFormat: 'csv',
      mapping: { ref: 'REF' },
      validationRules: null,
      isActive: true,
    })
  })

  /**
   * Un JSON invalide n'est pas envoyé : le serveur le refuserait en 422 sur un
   * champ que l'écran n'aurait pas signalé. L'erreur de `JSON.parse` indique la
   * position fautive.
   */
  it('refuse d’envoyer un JSON invalide', async () => {
    const onSubmit = vi.fn<Submit>(async () => {})
    render(onSubmit)

    await fillRequired()
    await userEvent.type(screen.getByLabelText(/^Correspondance des champs/), '{{ref: ')

    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => expect(onSubmit).not.toHaveBeenCalled())
    expect(await screen.findByText(/configuration JSON est invalide/)).toBeInTheDocument()
  })

  /** Un champ vide est légitime : les deux colonnes sont nullables. */
  it('accepte des configurations vides', async () => {
    const onSubmit = vi.fn<Submit>(async () => {})
    render(onSubmit)

    await fillRequired()
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    expect(onSubmit.mock.calls[0][0]).toMatchObject({
      mapping: null,
      validationRules: null,
    })
  })

  it('reformate un JSON valide à la demande', async () => {
    render(async () => {})

    const editor = await screen.findByLabelText(/^Règles de validation/)
    await userEvent.type(editor, '{{"a":1}')

    // Deux éditeurs sont à l'écran ; le second bouton « Formater » est le sien.
    await userEvent.click(screen.getAllByRole('button', { name: /Formater/ })[1])

    await waitFor(() => expect((editor as HTMLTextAreaElement).value).toContain('\n  "a": 1'))
  })
})

describe('référence des champs acceptés', () => {
  /**
   * Le §12 : éditeur contrôlé **et documentation**. Sans elle, l'écran demande
   * un JSON sans dire quelles clés sont valides.
   */
  it('documente les champs que Tricolis accepte', async () => {
    render(async () => {})

    await userEvent.click(await screen.findByRole('button', { name: /Champs acceptés/ }))

    // `externalReference` existe deux fois — au niveau commande et au niveau
    // ligne : les noms sont donc visés exactement.
    expect(
      await screen.findByRole('button', { name: 'Copier lines[].quantity' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Copier externalReference' })).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Copier lines[].externalReference' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Copier packages[].barcode' })).toBeInTheDocument()
  })

  /**
   * Commande et réclamation sont deux documents distincts, servis par deux
   * endpoints. Les fondre laisserait croire qu'un même fichier porte des colis
   * et un type de réclamation.
   */
  it('sépare les deux cibles d’import', async () => {
    render(async () => {})

    await userEvent.click(await screen.findByRole('button', { name: /Champs acceptés/ }))

    expect(await screen.findByText('Import de commandes')).toBeInTheDocument()
    expect(screen.getByText('Import de réclamations')).toBeInTheDocument()

    // Les services sont la section la plus exigeante d'une commande : presque
    // tous leurs champs sont obligatoires.
    expect(
      screen.getByRole('button', { name: 'Copier services[].serviceNumber' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Copier claimType' })).toBeInTheDocument()
  })

  /**
   * Le point le plus facile à manquer : rien ne lit encore ce mapping. Le taire
   * laisserait croire qu'enregistrer déclenche quelque chose.
   */
  it('dit que la correspondance n’est pas exécutée', async () => {
    render(async () => {})

    await userEvent.click(await screen.findByRole('button', { name: /Champs acceptés/ }))

    expect(await screen.findByText(/pas exécutée/)).toBeInTheDocument()
  })
})
