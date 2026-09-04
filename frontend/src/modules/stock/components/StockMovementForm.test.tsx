import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StockMovementForm } from './StockMovementForm'
import {
  toStockMovementPayload,
  type StockMovementFormValues,
} from '../schemas/stockMovementSchema'
import {
  CHILD_LOCATION_ID,
  ITEM_ID,
  LOCATION_ID,
  serveCustomers,
  stockItem,
  stockLocation,
} from '../testSupport'

function serve() {
  serveCustomers()
  server.use(
    http.get(`${API}/stock-items`, () => HttpResponse.json(paginated([stockItem()]))),
    http.get(`${API}/stock-locations`, () =>
      HttpResponse.json(
        paginated([
          stockLocation(),
          stockLocation({ id: CHILD_LOCATION_ID, locationCode: 'B-02-1-1', zoneCode: 'B' }),
        ]),
      ),
    ),
  )
}

function render(onSubmit: (values: StockMovementFormValues) => Promise<unknown>) {
  renderWithProviders(
    <StockMovementForm onSubmit={onSubmit} onCancel={() => {}} submitLabel="Enregistrer" />,
    { membership: withPermissions(['stock_movements.create']) },
  )
}

/** Choisit le client puis l'article : l'un conditionne l'autre. */
async function pickItem() {
  await userEvent.click(screen.getByLabelText('Client'))
  await userEvent.click(await screen.findByRole('option', { name: /Client Nord/ }))
  await userEvent.click(screen.getByLabelText(/^Code article/))
  await userEvent.click(await screen.findByRole('option', { name: /PAL-EUR/ }))
}

describe('formulaire de mouvement', () => {
  /**
   * Le sens n'est pas stocké : il se déduit des emplacements. Une entrée n'a
   * pas d'origine, et le champ ne doit donc pas être proposé.
   */
  it('ne demande pas d’origine pour une entrée', async () => {
    serve()
    render(async () => {})

    expect(await screen.findByLabelText(/Emplacement de destination/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/Emplacement d’origine/)).not.toBeInTheDocument()
  })

  it('demande les deux extrémités pour un transfert', async () => {
    serve()
    render(async () => {})

    await userEvent.click(await screen.findByLabelText(/^Sens/))
    await userEvent.click(await screen.findByRole('option', { name: /Transfert/ }))

    expect(await screen.findByLabelText(/Emplacement d’origine/)).toBeInTheDocument()
    expect(screen.getByLabelText(/Emplacement de destination/)).toBeInTheDocument()
  })

  /**
   * Un transfert part en **une** soumission : débiter puis créditer en deux
   * requêtes laisserait une fenêtre où la marchandise n'existe nulle part.
   */
  it('soumet un transfert en une seule charge utile', async () => {
    serve()
    const onSubmit = vi.fn<(values: StockMovementFormValues) => Promise<void>>(async () => {})
    render(onSubmit)

    await pickItem()

    await userEvent.click(screen.getByLabelText(/^Sens/))
    await userEvent.click(await screen.findByRole('option', { name: /Transfert/ }))

    await userEvent.click(await screen.findByLabelText(/Emplacement d’origine/))
    await userEvent.click(await screen.findByRole('option', { name: /A-01-2-3/ }))
    await userEvent.click(screen.getByLabelText(/Emplacement de destination/))
    await userEvent.click(await screen.findByRole('option', { name: /B-02-1-1/ }))

    await userEvent.type(screen.getByLabelText(/^Quantité/), '30')
    await userEvent.type(screen.getByLabelText(/^Type/), 'transfert')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))

    expect(toStockMovementPayload(onSubmit.mock.calls[0][0])).toEqual({
      stockItemId: ITEM_ID,
      sourceLocationId: LOCATION_ID,
      destinationLocationId: CHILD_LOCATION_ID,
      movementType: 'transfert',
      quantity: 30,
    })
  })

  /** `quantity` est `gt:0` côté serveur ; l'écran refuse avant l'aller-retour. */
  it('refuse une quantité nulle ou négative', async () => {
    serve()
    const onSubmit = vi.fn<(values: StockMovementFormValues) => Promise<void>>(async () => {})
    render(onSubmit)

    await pickItem()
    await userEvent.click(await screen.findByLabelText(/Emplacement de destination/))
    await userEvent.click(await screen.findByRole('option', { name: /A-01-2-3/ }))
    await userEvent.type(screen.getByLabelText(/^Quantité/), '0')
    await userEvent.type(screen.getByLabelText(/^Type/), 'reception')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(
      await screen.findByText('La quantité d’un mouvement doit être strictement positive.'),
    ).toBeInTheDocument()
    expect(onSubmit).not.toHaveBeenCalled()
  })

  /**
   * `CreateStockMovementAction` impose au moins une extrémité. L'écran le
   * refuse d'avance plutôt que de laisser partir une requête vouée au 422.
   */
  it('refuse un mouvement sans aucun emplacement', async () => {
    serve()
    const onSubmit = vi.fn<(values: StockMovementFormValues) => Promise<void>>(async () => {})
    render(onSubmit)

    await pickItem()
    await userEvent.type(screen.getByLabelText(/^Quantité/), '10')
    await userEvent.type(screen.getByLabelText(/^Type/), 'reception')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(onSubmit).not.toHaveBeenCalled())
  })
})
