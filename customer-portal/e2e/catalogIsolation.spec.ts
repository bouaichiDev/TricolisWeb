import { expect, test } from '@playwright/test'

import { apiGet, apiSend, apiToken, IKEA_ADMIN, login, QOQA_ADMIN } from './support'

/**
 * Isolation de l'écriture du catalogue : IKEA ne lit, ne modifie, n'active ni
 * ne désactive un article QOQA — ni par l'écran, ni par l'API. Catalogue QOQA
 * de `DemoCustomerPortalCatalogSeeder`.
 */
test('IKEA can neither read nor change a QOQA catalogue article', async ({ page }) => {
  const qoqa = await apiToken(QOQA_ADMIN)
  const ikea = await apiToken(IKEA_ADMIN)
  const article = (await (await apiGet(qoqa, '/catalog/items?search=QOQA-TABLE-80')).json()).data[0] as { id: string; name: string; status: string }
  expect(article, 'article QOQA de démonstration (DemoCustomerPortalCatalogSeeder)').toBeTruthy()

  // API : chaque écriture répond comme pour un article inexistant.
  expect((await apiSend(ikea, 'PATCH', `/catalog/items/${article.id}`, { name: 'Piraté' })).status()).toBe(404)
  expect((await apiSend(ikea, 'POST', `/catalog/items/${article.id}/deactivate`)).status()).toBe(404)
  expect((await apiGet(ikea, `/catalog/items/${article.id}`)).status()).toBe(404)
  expect((await apiSend(ikea, 'PATCH', '/catalog/items/01JZZZZZZZZZZZZZZZZZZZZZZZ', { name: 'x' })).status()).toBe(404)

  // QOQA retrouve son article intact.
  const after = (await (await apiGet(qoqa, `/catalog/items/${article.id}`)).json()).data as { name: string; status: string }
  expect(after).toMatchObject({ name: article.name, status: article.status })

  // Écran : l'URL directe ne montre rien, et la liste IKEA ne le porte pas.
  await login(page, IKEA_ADMIN)
  await page.goto(`/catalog/${article.id}`)
  await expect(page.getByText('Article introuvable')).toBeVisible()
  await page.goto('/catalog')
  await expect(page.getByRole('link', { name: 'BILLY-80' })).toBeVisible()
  await expect(page.getByText('QOQA-TABLE-80')).toHaveCount(0)
})
