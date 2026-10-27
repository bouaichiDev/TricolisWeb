import { expect, test } from '@playwright/test'

import { IKEA_ADMIN, login } from './support'

/**
 * Une demande de correction créée depuis la page des demandes, sans passer
 * par la fiche article : choix de l'article par recherche, puis demande. Le
 * stock affiché ne change pas tant que le transporteur n'a pas décidé.
 */
test('an IKEA admin asks for a stock correction from the requests page', async ({ page }) => {
  const reason = `Inventaire E2E ${Date.now()}`

  await login(page, IKEA_ADMIN)
  await page.goto('/stock/adjustment-requests')
  await page.getByRole('button', { name: 'Nouvelle demande' }).click()

  const picker = page.getByRole('dialog')
  await picker.getByLabel('Rechercher un article').fill('BILLY')
  await picker.getByRole('button', { name: /BILLY-80/ }).click()

  const dialog = page.getByRole('dialog', { name: /Demander une correction — BILLY-80/ })
  await dialog.getByLabel('Quantité constatée').fill('9')
  await dialog.getByLabel('Motif').fill(reason)
  await dialog.getByRole('button', { name: 'Envoyer la demande' }).click()

  await expect(page.getByRole('cell', { name: reason })).toBeVisible()
  await expect(page.getByText('En attente de votre transporteur').first()).toBeVisible()
})
