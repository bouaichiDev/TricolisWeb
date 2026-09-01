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
      await screen.findByRole('button', { name: 'Ajouter lines[].quantity à la correspondance' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Ajouter externalReference à la correspondance' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', {
        name: 'Ajouter lines[].externalReference à la correspondance',
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Ajouter packages[].barcode à la correspondance' }),
    ).toBeInTheDocument()
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
      screen.getByRole('button', { name: 'Ajouter services[].serviceNumber à la correspondance' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Ajouter claimType à la correspondance' }),
    ).toBeInTheDocument()
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

describe('assistant de correspondance', () => {
  const open = async () =>
    userEvent.click(await screen.findByRole('button', { name: /Champs acceptés/ }))

  const editor = () => screen.getByLabelText(/^Correspondance des champs/) as HTMLTextAreaElement

  /** Un éditeur vide n'a pas d'objet : le premier clic le crée. */
  it('crée le document au premier champ ajouté', async () => {
    render(async () => {})
    await open()

    await userEvent.click(
      screen.getByRole('button', { name: 'Ajouter orderDate à la correspondance' }),
    )

    expect(JSON.parse(editor().value)).toEqual({ orderDate: '' })
  })

  /**
   * Le cas qui décourage sans assistant : un chemin imbriqué s'écrit en entier
   * depuis la racine, avec ses tableaux.
   */
  it('déploie la structure d’un champ imbriqué', async () => {
    render(async () => {})
    await open()

    await userEvent.click(
      screen.getByRole('button', {
        name: 'Ajouter services[].contacts[].phone à la correspondance',
      }),
    )

    expect(JSON.parse(editor().value)).toEqual({
      services: [{ contacts: [{ phone: '' }] }],
    })
  })

  /** Deux champs de suite se complètent au lieu de s'écraser. */
  it('ajoute un second champ sans effacer le premier', async () => {
    render(async () => {})
    await open()

    await userEvent.click(
      screen.getByRole('button', { name: 'Ajouter lines[].articleCode à la correspondance' }),
    )
    await userEvent.click(
      screen.getByRole('button', { name: 'Ajouter lines[].quantity à la correspondance' }),
    )

    expect(JSON.parse(editor().value)).toEqual({
      lines: [{ articleCode: '', quantity: '' }],
    })
  })

  /** Sur un document cassé, l'assistant le dit plutôt que de rester inerte. */
  it('refuse d’écrire dans un JSON invalide', async () => {
    render(async () => {})
    await open()

    await userEvent.type(editor(), '{{"a": ')
    await userEvent.click(
      screen.getByRole('button', { name: 'Ajouter orderDate à la correspondance' }),
    )

    expect(await screen.findByText(/n’est pas un JSON valide/)).toBeInTheDocument()
  })
})
