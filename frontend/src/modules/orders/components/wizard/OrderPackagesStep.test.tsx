import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'

import { useOrderDraft } from '../../hooks/useOrderDraft'
import { emptyDraft, emptyLine } from '../../schemas/orderDraft'
import type { OrderErrorReport } from '../../schemas/orderErrors'
import { serveWizardScope } from '../../pages/wizardScope'
import { OrderPackagesStep } from './OrderPackagesStep'

const EMPTY: OrderErrorReport = { issues: [], stepsInError: [], message: null }

/** Monte l'étape avec un brouillon d'une seule ligne de dix unités. */
function Harness() {
  const controller = useOrderDraft({
    ...emptyDraft(),
    lines: [{ ...emptyLine(), name: 'Carton renforcé', quantity: '10' }],
  })

  return <OrderPackagesStep controller={controller} report={EMPTY} />
}

const render = () =>
  renderWithProviders(<Harness />, { membership: withPermissions(['orders.create']) })

describe('OrderPackagesStep', () => {
  it('dit que les colis sont facultatifs tant qu’aucun n’est déclaré', () => {
    serveWizardScope()
    render()

    expect(screen.getByText(/peut être créée sans aucun colis/i)).toBeInTheDocument()
  })

  /**
   * Les trois nombres — commandé, affecté, reste — sont ce qui permet de
   * répartir une ligne entre plusieurs colis sans dépasser la quantité
   * commandée, contrainte que `PackageLineAllocator` fait respecter côté
   * serveur.
   */
  it('affiche commandé, affecté et reste, et suit la saisie', async () => {
    serveWizardScope()
    render()

    await userEvent.click(screen.getAllByRole('button', { name: 'Ajouter un colis' })[0])

    // Une seule ligne : le premier colis la reçoit entière (voir ci-dessous).
    expect(
      await screen.findByText(/Commandé 10 · Affecté 10 · Reste à affecter 0/),
    ).toBeInTheDocument()

    await userEvent.clear(screen.getByLabelText('Quantité affectée'))
    await userEvent.type(screen.getByLabelText('Quantité affectée'), '4')

    await waitFor(() => {
      expect(screen.getByText(/Commandé 10 · Affecté 4 · Reste à affecter 6/)).toBeInTheDocument()
    })
  })

  /**
   * Le contenu d'un colis naît avec la commande : `CreateOrderPackages` lit
   * `packages[].lines[]` et rien d'autre ne le renseigne avant l'exécution.
   * Avec une seule ligne, tout y va — et cela se décide **ici**, au moment où
   * l'utilisateur déclare le contenu, pas plus tard en consultant la fiche.
   */
  it('met la ligne unique dans le premier colis ajouté', async () => {
    serveWizardScope()
    render()

    await userEvent.click(screen.getAllByRole('button', { name: 'Ajouter un colis' })[0])

    expect(await screen.findByLabelText('Quantité affectée')).toHaveValue(10)
  })

  /** Le second colis n'hérite de rien : la répartition, elle, est un choix. */
  it('laisse le second colis vide', async () => {
    serveWizardScope()
    render()

    await userEvent.click(screen.getAllByRole('button', { name: 'Ajouter un colis' })[0])
    await userEvent.click(screen.getAllByRole('button', { name: 'Ajouter un colis' })[0])

    const fields = await screen.findAllByLabelText('Quantité affectée')

    expect(fields).toHaveLength(2)
    expect(fields[1]).toHaveValue(null)
  })

  /**
   * Au-delà du premier colis, la répartition est un choix : dix chaises peuvent
   * tenir sur trois palettes. « Tout affecter » évite de recopier le reste à la
   * main sans décider à la place de qui répartit.
   */
  it('affecte tout le reste au colis en un clic', async () => {
    serveWizardScope()
    render()

    await userEvent.click(screen.getAllByRole('button', { name: 'Ajouter un colis' })[0])
    await userEvent.clear(await screen.findByLabelText('Quantité affectée'))

    await userEvent.click(
      await screen.findByRole('button', { name: 'Tout affecter à ce colis' }),
    )

    await waitFor(() => {
      expect(
        screen.getByText(/Commandé 10 · Affecté 10 · Reste à affecter 0/),
      ).toBeInTheDocument()
    })

    // Plus rien à affecter : le bouton disparaît plutôt que de rester inerte.
    expect(
      screen.queryByRole('button', { name: 'Tout affecter à ce colis' }),
    ).not.toBeInTheDocument()
  })

  it('signale un dépassement de la quantité commandée', async () => {
    serveWizardScope()
    render()

    await userEvent.click(screen.getAllByRole('button', { name: 'Ajouter un colis' })[0])
    await userEvent.type(await screen.findByLabelText('Quantité affectée'), '12')

    await waitFor(() => {
      expect(screen.getByLabelText('Quantité affectée')).toHaveAttribute('aria-invalid', 'true')
    })
  })

  it('imbrique un colis sous son parent', async () => {
    serveWizardScope()
    render()

    await userEvent.click(screen.getAllByRole('button', { name: 'Ajouter un colis' })[0])
    await userEvent.click(
      await screen.findByRole('button', { name: 'Ajouter un colis à l’intérieur' }),
    )

    expect(await screen.findByText('Colis 2')).toBeInTheDocument()
  })

  it('retire un colis avec les colis qu’il contient', async () => {
    serveWizardScope()
    render()

    await userEvent.click(screen.getAllByRole('button', { name: 'Ajouter un colis' })[0])
    await userEvent.click(
      await screen.findByRole('button', { name: 'Ajouter un colis à l’intérieur' }),
    )
    await screen.findByText('Colis 2')

    await userEvent.click(screen.getAllByRole('button', { name: 'Retirer le colis' })[0])

    expect(screen.queryByText('Colis 1')).not.toBeInTheDocument()
    expect(screen.queryByText('Colis 2')).not.toBeInTheDocument()
    expect(screen.getByText(/peut être créée sans aucun colis/i)).toBeInTheDocument()
  })
})
