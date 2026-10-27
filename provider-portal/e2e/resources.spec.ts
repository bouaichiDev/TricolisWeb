import { expect, test } from '@playwright/test'

import { ALPHA_ADMIN, API, BETA_ADMIN, apiGet, apiToken, login } from './support'

/**
 * Phase 2 : le fournisseur gère ses chauffeurs et ses véhicules, et seulement
 * les siens. Préalable : `DemoProviderPortalFleetSeeder` (BETA-D1 existe).
 */
test('Alpha ajoute un chauffeur et un véhicule, puis met le véhicule au repos', async ({ page }) => {
  const stamp = Date.now().toString().slice(-6)
  await login(page, ALPHA_ADMIN)

  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Chauffeurs' }).click()
  await page.getByRole('button', { name: 'Ajouter un chauffeur' }).click()
  const driverDialog = page.getByRole('dialog')
  await driverDialog.getByLabel('Code').fill(`E2E-D${stamp}`)
  await driverDialog.getByLabel('Prénom').fill('Elise')
  await driverDialog.getByLabel('Nom', { exact: true }).fill('Essai')
  await driverDialog.getByLabel('E-mail').fill(`elise.${stamp}@alpha.test`)
  await driverDialog.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(page.getByRole('cell', { name: `E2E-D${stamp}` })).toBeVisible()

  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Véhicules' }).click()
  await page.getByRole('button', { name: 'Ajouter un véhicule' }).click()
  const vehicleDialog = page.getByRole('dialog')
  await vehicleDialog.getByLabel('Code').fill(`E2E-V${stamp}`)
  await vehicleDialog.getByLabel('Immatriculation').fill(`E2E-${stamp}`)
  await vehicleDialog.getByLabel('Type de véhicule').selectOption({ index: 1 })
  await vehicleDialog.getByLabel('Charge utile (kg)').fill('1000')
  await vehicleDialog.getByLabel('Volume (m³)').fill('10')
  await vehicleDialog.getByLabel('Palettes').fill('3')
  await vehicleDialog.getByRole('button', { name: 'Enregistrer' }).click()

  const row = page.getByRole('row').filter({ hasText: `E2E-V${stamp}` })
  await expect(row).toBeVisible()
  await row.getByRole('button', { name: 'Désactiver' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Désactiver' }).click()
  await expect(row.getByText('Inactif')).toBeVisible()
})

test('Alpha ne peut pas lire le chauffeur de Beta', async () => {
  const beta = await (await apiGet(await apiToken(BETA_ADMIN), `${API}/drivers?search=BETA-D1`)).json()
  const id = beta.data[0].id as string

  expect((await apiGet(await apiToken(ALPHA_ADMIN), `${API}/drivers/${id}`)).status()).toBe(404)
})
