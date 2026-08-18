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

    expect(await screen.findByText(/Commandé 10 · Affecté 0 · Reste à affecter 10/)).toBeInTheDocument()

    await userEvent.type(screen.getByLabelText('Quantité affectée'), '4')

    await waitFor(() => {
      expect(screen.getByText(/Commandé 10 · Affecté 4 · Reste à affecter 6/)).toBeInTheDocument()
    })
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
