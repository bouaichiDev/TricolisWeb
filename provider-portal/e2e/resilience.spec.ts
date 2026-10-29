import { expect, test } from '@playwright/test'

import { ALPHA_ADMIN, login } from './support'

/**
 * Phase 8 — ce que voit le fournisseur quand quelque chose casse : réseau
 * coupé puis rétabli, session expirée ou révoquée.
 */
test('une coupure réseau affiche une erreur, puis « Réessayer » recharge', async ({ page }) => {
  await login(page, ALPHA_ADMIN)
  await page.route('**/api/v1/provider/tours?**', (route) => route.abort('internetdisconnected'))

  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Tournées' }).click()
  const retry = page.getByRole('button', { name: 'Réessayer' })
  await expect(retry).toBeVisible()

  await page.unroute('**/api/v1/provider/tours?**')
  await retry.click()
  await expect(retry).toHaveCount(0)
  await expect(page.getByRole('tab', { name: 'À effectuer' })).toBeVisible()
})

test('une session expirée ou révoquée ramène à la connexion', async ({ page }) => {
  await login(page, ALPHA_ADMIN)
  await page.evaluate(() => sessionStorage.setItem('tricolis.provider-portal.token', '999|jeton-expire-ou-revoque'))

  await page.goto('/tours')

  await expect(page.getByRole('heading', { name: 'Connexion au portail fournisseur' })).toBeVisible()
  expect(await page.evaluate(() => sessionStorage.getItem('tricolis.provider-portal.token'))).toBeNull()
})
