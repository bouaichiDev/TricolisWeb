import { expect, test } from '@playwright/test'

import { ALPHA_ADMIN, login } from './support'

/**
 * Phase 4 : Alpha exécute sa tournée de l'après-midi, déjà affectée —
 * démarrer, arriver à l'arrêt, le terminer, terminer la tournée. Préalables :
 * `DemoProviderPortalFleetSeeder` puis `DemoProviderPortalTourSeeder`.
 */
test('Alpha exécute sa tournée de bout en bout', async ({ page }) => {
  await login(page, ALPHA_ADMIN)

  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Tournées' }).click()
  await page.getByRole('link', { name: 'DEMO-ALPHA-2' }).click()
  await expect(page.getByRole('heading', { name: 'Tournée DEMO-ALPHA-2' })).toBeVisible()

  await page.getByRole('button', { name: 'Démarrer' }).click()
  await page.getByRole('button', { name: 'Arrivé' }).click()
  await page.getByRole('button', { name: 'Terminé', exact: true }).click()
  await page.getByRole('button', { name: 'Terminer la tournée' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Terminer' }).click()

  await expect(page.getByText('Terminée').first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Démarrer' })).toHaveCount(0)

  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Tournées' }).click()
  await page.getByRole('tab', { name: 'Terminées' }).click()
  await expect(page.getByRole('link', { name: 'DEMO-ALPHA-2' })).toBeVisible()
})
