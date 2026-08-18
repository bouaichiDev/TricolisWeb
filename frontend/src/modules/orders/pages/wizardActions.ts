import { fireEvent, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

/**
 * Ouvre une liste déroulante Radix par son libellé, puis choisit une option.
 *
 * Le nom accessible d'une option agrège son libellé et son code — « Client
 * Alpha CLI001 » — d'où la recherche par motif plutôt que par égalité.
 */
export async function pick(label: RegExp, option: RegExp) {
  await userEvent.click(screen.getByLabelText(label))
  const listbox = await screen.findByRole('listbox')
  await userEvent.click(within(listbox).getByRole('option', { name: option }))
}

/** Les boutons du fil portent leur rang avant leur nom : « 1 Général ». */
export const goTo = async (step: string) =>
  userEvent.click(screen.getByRole('button', { name: new RegExp(`${step}$`) }))

const setDate = (label: RegExp, value: string) =>
  fireEvent.change(screen.getByLabelText(label), { target: { value } })

/** Remplit le strict nécessaire, avec ou sans article de catalogue. */
export async function fillOrder({ fromCatalog = false } = {}) {
  await pick(/^Client/, /Client Alpha/)
  await pick(/^Agence/, /Agence Nord/)

  await goTo('Lignes')

  if (fromCatalog) {
    await userEvent.click(await screen.findByRole('button', { name: /Choisir dans le catalogue/ }))
    await pick(/^Catalogues/, /Catalogue général/)
    await userEvent.click(await screen.findByRole('button', { name: /Carton renforcé/ }))
  } else {
    await userEvent.type(screen.getByLabelText(/^Libellé/), 'Palette de cartons')
  }

  await goTo('Services')

  await pick(/^Service \*/, /Livraison standard/)
  await userEvent.type(screen.getByLabelText(/^N° service/), 'SRV-1')
  await pick(/^Adresse \*/, /Entrepôt Casablanca/)
  setDate(/^Date demandée/, '2026-09-01')

  for (const [label, value] of [
    [/^PU client/, '120'],
    [/^Total client/, '120'],
    [/^PU fournisseur/, '80'],
    [/^Total fournisseur/, '80'],
  ] as const) {
    await userEvent.type(screen.getByLabelText(label), value)
  }
}
