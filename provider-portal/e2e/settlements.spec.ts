import { expect, test } from '@playwright/test'

import { ALPHA_ADMIN, API, apiGet, apiToken, login } from './support'

/**
 * Phase 6 : Alpha lit ses décomptes clôturés — ni son brouillon, ni ceux de
 * Beta — et ses communications reçues, sans rien pouvoir modifier.
 * Préalable : `DemoProviderPortalTourSeeder` (qui appelle celui des décomptes).
 */
test('Alpha ne lit que ses décomptes clôturés', async ({ page }) => {
  await login(page, ALPHA_ADMIN)
  const nav = page.getByRole('navigation', { name: 'Navigation principale' })

  await nav.getByRole('link', { name: 'Décomptes' }).click()
  await expect(page.getByRole('link', { name: 'DEMO-STL-ALPHA-1' })).toBeVisible()
  await expect(page.getByText('DEMO-STL-ALPHA-2')).toHaveCount(0)
  await expect(page.getByText('DEMO-STL-BETA-1')).toHaveCount(0)

  await page.getByRole('link', { name: 'DEMO-STL-ALPHA-1' }).click()
  await expect(page.getByRole('heading', { name: 'Décompte DEMO-STL-ALPHA-1' })).toBeVisible()
  await expect(page.getByRole('list', { name: 'Lignes du décompte' }).getByText('Livraison')).toBeVisible()
  await expect(page.getByRole('button', { name: /modifier|valider|recalculer|supprimer/i })).toHaveCount(0)

  await nav.getByRole('link', { name: 'Communications' }).click()
  await expect(page.getByRole('heading', { name: 'Communications' })).toBeVisible()
})

test('l’API ne rend à Alpha ni son brouillon ni le décompte de Beta', async () => {
  const token = await apiToken(ALPHA_ADMIN)
  const list = await (await apiGet(token, `${API}/settlements`)).json()
  const numbers = list.data.map((row: { settlementNumber: string }) => row.settlementNumber)

  expect(numbers).toContain('DEMO-STL-ALPHA-1')
  expect(numbers).not.toContain('DEMO-STL-ALPHA-2')
  expect(numbers).not.toContain('DEMO-STL-BETA-1')
})
