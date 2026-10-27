import { expect, test } from '@playwright/test'

import { apiGet, apiToken, dismissToasts, IKEA_ADMIN, login } from './support'

/**
 * Scénarios de la Phase 3 (§45) : calendrier, suivi, lien public.
 *
 * Les données sont celles de `CustomerPortalDemoSeeder` : une commande IKEA
 * demandée à J+3, non planifiée. La carte et l'ETA, qui exigent une tournée
 * en cours, sont couvertes par les tests Pest et Vitest.
 */
async function demoOrder() {
  const token = await apiToken(IKEA_ADMIN)
  const list = await (await apiGet(token, '/orders?customerReference=IKEA-DEMO-1')).json()

  return list.data[0] as { id: string; reference: string; status: { label: string } }
}

test('the calendar shows the IKEA order and leads to its detail and tracking', async ({ page }) => {
  const order = await demoOrder()

  await login(page, IKEA_ADMIN)
  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Calendrier' }).click()
  await expect(page.getByRole('heading', { name: 'Calendrier' })).toBeVisible()

  // Filtrer par le statut de la commande : elle reste affichée.
  await page.getByRole('combobox', { name: 'Statut' }).click()
  await page.getByRole('option', { name: order.status.label }).click()
  await expect(page).toHaveURL(/status=/)

  await page.getByText(order.reference).first().click()
  const sheet = page.getByRole('dialog')
  await expect(sheet.getByText(order.reference)).toBeVisible()
  await sheet.getByRole('link', { name: 'Voir la commande' }).click()
  // Le titre de la vue rapide porte aussi la référence : on attend la page.
  await expect(page).toHaveURL(new RegExp(`/orders/${order.id}$`))
  await expect(page.getByRole('heading', { level: 1, name: order.reference })).toBeVisible()
  await expect(sheet).toHaveCount(0)

  // Retour au calendrier, puis le suivi depuis la vue rapide.
  await page.goBack()
  await page.getByText(order.reference).first().click()
  await page.getByRole('dialog').getByRole('link', { name: 'Suivre' }).click()
  await expect(page.getByRole('heading', { name: `Suivi ${order.reference}` })).toBeVisible()
})

test('the tracking page tells what is known and what is not', async ({ page }) => {
  const order = await demoOrder()

  await login(page, IKEA_ADMIN)
  await page.goto(`/orders/${order.id}`)
  await page.getByRole('link', { name: 'Suivre' }).click()

  await expect(page.getByRole('heading', { name: `Suivi ${order.reference}` })).toBeVisible()
  await expect(page.getByRole('list', { name: 'Timeline de suivi' }).getByText('Commande créée')).toBeVisible()
  await expect(page.getByText('ETA indisponible')).toBeVisible()
  await expect(page.getByTestId('position-status')).toHaveText('Position indisponible')
  await expect(page.getByText('Tracking indisponible')).toBeVisible()
})

test('a public link works without an account, then stops once revoked', async ({ page, browser }) => {
  const order = await demoOrder()

  await login(page, IKEA_ADMIN)
  await page.goto(`/orders/${order.id}/tracking`)

  // Un lien peut rester d'une exécution précédente : on le régénère alors.
  const section = page.getByTestId('public-link-state')
  await expect(section).toBeVisible()
  if ((await section.getAttribute('data-state')) === 'active') {
    await section.getByRole('button', { name: 'Régénérer' }).click()
  } else {
    await section.getByRole('button', { name: /Générer (un|un nouveau) lien/ }).click()
  }

  const url = await page.getByRole('textbox', { name: 'Lien de suivi public' }).inputValue()
  expect(url).toMatch(/\/t\/[A-Za-z0-9]{64}$/)

  // « Fenêtre privée » : un contexte neuf, sans session du portail.
  const anonymous = await browser.newContext({ locale: 'fr-FR' })
  const visitor = await anonymous.newPage()
  await visitor.goto(url)
  await expect(visitor.getByRole('heading', { name: /Commande/ })).toBeVisible()
  await expect(visitor.getByText('Commande créée')).toBeVisible()
  await expect(visitor.getByRole('navigation', { name: 'Navigation principale' })).toHaveCount(0)

  // Révocation depuis le portail, puis rafraîchissement du lien public.
  await dismissToasts(page)
  page.once('dialog', (dialog) => void dialog.accept())
  await page.getByRole('button', { name: 'Révoquer' }).click()
  await expect(section).toHaveAttribute('data-state', 'revoked')

  await visitor.reload()
  await expect(visitor.getByText('Tracking indisponible')).toBeVisible()
  await anonymous.close()
})
