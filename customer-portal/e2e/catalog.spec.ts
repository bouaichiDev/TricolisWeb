import { expect, test } from '@playwright/test'

import { dismissToasts, IKEA_ADMIN, login } from './support'

/**
 * Le catalogue du portail (permissions de la Phase 1, livré en Phase 8), sur
 * le catalogue IKEA de `CustomerPortalDemoSeeder` (BILLY-80, KALLAX-4X4…).
 */
test('an IKEA admin browses the catalogue and adds an article', async ({ page }) => {
  const code = `E2E-CAT-${Date.now()}`.slice(0, 64)

  await login(page, IKEA_ADMIN)
  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Catalogue' }).click()

  await expect(page.getByRole('heading', { name: 'Catalogue' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'BILLY-80' })).toBeVisible()

  await page.getByRole('button', { name: /Ajouter un article/ }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Code article').fill(code)
  await dialog.getByLabel('Désignation').fill('Article de parcours')
  await dialog.getByLabel('Poids (kg)').fill('3,5')
  await dialog.getByRole('button', { name: 'Ajouter' }).click()

  await expect(page.getByRole('heading', { name: code })).toBeVisible()
  await expect(page.getByText('3,500 kg')).toBeVisible()
  await expect(page.getByText('Lignes de commande')).toBeVisible()

  // Modifier, puis désactiver et réactiver l'article créé.
  await dismissToasts(page)
  await page.getByRole('button', { name: 'Modifier' }).click()
  const edit = page.getByRole('dialog')
  await expect(edit.getByLabel('Poids (kg)')).toHaveValue('3,5')
  await edit.getByLabel('Désignation').fill('Article de parcours modifié')
  await edit.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(page.getByText('Article de parcours modifié').first()).toBeVisible()

  await dismissToasts(page)
  page.once('dialog', (confirm) => void confirm.accept())
  await page.getByRole('button', { name: 'Désactiver' }).click()
  await expect(page.getByText('Inactif')).toBeVisible()
  await dismissToasts(page)
  await page.getByRole('button', { name: 'Réactiver' }).click()
  await expect(page.getByText('Actif', { exact: true })).toBeVisible()
})

test('a viewer reads the catalogue but cannot add to it', async ({ page }) => {
  await login(page, 'viewer@ikea.test')
  await page.goto('/catalog')

  await expect(page.getByRole('link', { name: 'BILLY-80' })).toBeVisible()
  await expect(page.getByRole('button', { name: /Ajouter un article/ })).toHaveCount(0)
})
