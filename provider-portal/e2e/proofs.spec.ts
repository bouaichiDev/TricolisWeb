import { expect, test } from '@playwright/test'

import { ALPHA_ADMIN, login } from './support'

/** Un PNG réel d'un pixel : le serveur lit le contenu, pas l'extension. */
const SIGNATURE = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64')

/**
 * Phase 5 : sur sa tournée en cours, Alpha saisit une preuve de livraison à un
 * arrêt atteint, puis la retrouve dans la chronologie et les documents.
 * Préalable : `DemoProviderPortalTourSeeder` (qui appelle celui des preuves).
 */
test('Alpha enregistre une preuve de livraison et retrouve la trace de sa tournée', async ({ page }) => {
  await login(page, ALPHA_ADMIN)

  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Tournées' }).click()
  await page.getByRole('tab', { name: 'En cours' }).click()
  await page.getByRole('link', { name: 'DEMO-ALPHA-3' }).click()
  await expect(page.getByRole('heading', { name: 'Tournée DEMO-ALPHA-3' })).toBeVisible()

  await page.getByRole('button', { name: 'Preuve de livraison' }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Réceptionnaire').fill('Marie Réception')
  await dialog.getByLabel('Signature (image)').setInputFiles({ name: 'signature.png', mimeType: 'image/png', buffer: SIGNATURE })
  await dialog.getByRole('button', { name: 'Enregistrer la preuve' }).click()

  await expect(page.getByText('Marie Réception')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Signature' })).toBeVisible()

  await page.getByRole('tab', { name: 'Chronologie' }).click()
  await expect(page.getByRole('list', { name: 'Chronologie' }).getByText('Tournée démarrée')).toBeVisible()

  await page.getByRole('tab', { name: 'Documents' }).click()
  await expect(page.getByText('signature.png')).toBeVisible()
})
