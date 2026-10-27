import { expect, test } from '@playwright/test'

import { ALPHA_ADMIN, API, BETA_ADMIN, apiGet, apiToken, login } from './support'

/**
 * Phase 3 : Alpha voit la tournée que le transporteur lui a attribuée et y
 * affecte son chauffeur et son véhicule. Préalables :
 * `DemoProviderPortalFleetSeeder` puis `DemoProviderPortalTourSeeder`.
 */
test('Alpha affecte son chauffeur et son véhicule à sa tournée', async ({ page }) => {
  await login(page, ALPHA_ADMIN)

  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Tournées' }).click()
  await page.getByRole('link', { name: 'DEMO-ALPHA-1' }).click()
  await expect(page.getByRole('heading', { name: 'Tournée DEMO-ALPHA-1' })).toBeVisible()

  await page.getByRole('button', { name: 'Affecter' }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Chauffeur').selectOption({ label: 'Adam Alpha (ALPHA-D1)' })
  await dialog.getByLabel('Véhicule').selectOption({ label: 'ALPHA-V1 · GE-ALPHA-1' })
  await dialog.getByRole('button', { name: 'Enregistrer' }).click()

  await expect(dialog).toBeHidden()
  await expect(page.getByText('Adam Alpha')).toBeVisible()
  await expect(page.getByText('ALPHA-V1 · GE-ALPHA-1')).toBeVisible()
})

test('Alpha ne voit pas la tournée de Beta', async () => {
  const beta = await (await apiGet(await apiToken(BETA_ADMIN), `${API}/tours?search=DEMO-BETA-1`)).json()
  const id = beta.data[0].id as string

  expect((await apiGet(await apiToken(ALPHA_ADMIN), `${API}/tours/${id}`)).status()).toBe(404)
})
