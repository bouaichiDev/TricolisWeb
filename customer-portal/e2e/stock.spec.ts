import { expect, test } from '@playwright/test'

import { apiGet, apiToken, IKEA_ADMIN, login, QOQA_ADMIN } from './support'

/**
 * Scénarios de la Phase 5 (§31), sur `DemoCustomerPortalStockSeeder` :
 * BILLY-80 d'IKEA, 50 en A-01-01 et 70 en B-02-03, 10 réservés pour la
 * commande de démonstration ; QOQA stocke 30 tables au même emplacement A.
 */
test('an IKEA admin reads its stock down to the linked order', async ({ page }) => {
  await login(page, IKEA_ADMIN)
  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Stock' }).click()

  const totals = page.getByLabel('Quantités')
  await expect(totals.getByText('120')).toBeVisible()
  await expect(totals.getByText('110')).toBeVisible()

  await page.getByPlaceholder('Code, code-barres, description…').fill('BILLY')
  await page.getByRole('link', { name: 'BILLY-80' }).click()

  const locations = page.getByRole('table', { name: 'Répartition par emplacement' })
  await expect(locations.getByText(/A-01-01/)).toBeVisible()
  await expect(locations.getByText(/B-02-03/)).toBeVisible()
  // L'emplacement A porte aussi les tables QOQA : rien n'en paraît.
  await expect(page.getByText('QOQA-TABLE')).toHaveCount(0)
  await expect(page.getByText('inbound').first()).toBeVisible()

  await page.getByRole('link', { name: /^ikea-/i }).first().click()
  await expect(page.getByRole('tab', { name: 'Stock réservé' })).toBeVisible()
})

test('an IKEA admin adds an item, which holds no stock until the carrier receives it', async ({ page }) => {
  const code = `E2E-${Date.now()}`.slice(0, 64)

  await login(page, IKEA_ADMIN)
  await page.goto('/stock')
  await page.getByRole('button', { name: /Ajouter un article/ }).click()

  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Code article').fill(code)
  await dialog.getByLabel('Description').fill('Article de test')
  await dialog.getByLabel('Statut').selectOption({ index: 1 })
  await dialog.getByRole('button', { name: 'Ajouter' }).click()

  await expect(page.getByRole('heading', { name: code })).toBeVisible()
  await expect(page.getByText('Aucun stock pour cet article')).toBeVisible()

  // Isolation : l'article d'IKEA n'existe pas pour QOQA.
  const qoqa = await apiToken(QOQA_ADMIN)
  const codes = ((await (await apiGet(qoqa, '/stock/items?perPage=100')).json()).data as { articleCode: string }[]).map((item) => item.articleCode)
  expect(codes).not.toContain(code)
})
