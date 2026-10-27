import { expect, test } from '@playwright/test'

import { apiGet, apiToken, IKEA_ADMIN, login, QOQA_ADMIN } from './support'

/**
 * Scénario de la Phase 4 : depuis un service de sa commande, IKEA signale un
 * problème, retrouve la réclamation dans sa liste — et QOQA ne la voit pas.
 *
 * Le statut initial vient du référentiel (`claim/open`, semé pour la démo).
 */
test('an IKEA admin reports a problem on a service and follows the claim', async ({ page }) => {
  const title = `E2E réclamation ${Date.now()}`
  const token = await apiToken(IKEA_ADMIN)
  const order = (await (await apiGet(token, '/orders?customerReference=IKEA-DEMO-1')).json()).data[0] as { id: string; reference: string }

  await login(page, IKEA_ADMIN)
  await page.goto(`/orders/${order.id}`)
  await page.getByRole('tab', { name: /Services/ }).click()
  await page.getByRole('link', { name: /Documents, livraison et réclamations/ }).first().click()

  await expect(page.getByText('Preuve de livraison')).toBeVisible()
  await page.getByRole('link', { name: /Signaler un problème/ }).click()

  // Commande et service sont imposés par la page d'origine.
  await expect(page.getByRole('heading', { name: 'Nouvelle réclamation' })).toBeVisible()
  await expect(page.getByText(order.reference)).toBeVisible()
  await page.getByLabel('Type de réclamation').fill('damage')
  await page.getByLabel('Titre').fill(title)
  await page.getByLabel('Description').fill('Carton ouvert à la livraison.')
  await page.getByRole('button', { name: 'Envoyer la réclamation' }).click()

  await expect(page.getByRole('heading', { name: title })).toBeVisible()
  await expect(page.getByText('Ouverte').first()).toBeVisible()

  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Réclamations' }).click()
  await expect(page.getByRole('link', { name: title })).toBeVisible()

  // Isolation : la réclamation d'IKEA n'existe pas pour QOQA.
  const qoqa = await apiToken(QOQA_ADMIN)
  const qoqaTitles = ((await (await apiGet(qoqa, '/claims')).json()).data as { title: string }[]).map((claim) => claim.title)
  expect(qoqaTitles).not.toContain(title)
})
