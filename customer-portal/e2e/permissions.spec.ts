import { expect, test } from '@playwright/test'

import { apiGet, apiToken, login } from './support'

/**
 * Refus de permission (Phase 8, E2E-021) : l'écran ne propose pas l'action,
 * l'URL directe aboutit à « Accès refusé », et l'API refuse elle aussi —
 * le masquage côté React n'est jamais la seule barrière.
 */
test('a viewer is refused user management on screen, by URL and by API', async ({ page }) => {
  await login(page, 'viewer@ikea.test')

  const navigation = page.getByRole('navigation', { name: 'Navigation principale' })
  await expect(navigation.getByRole('link', { name: 'Commandes' })).toBeVisible()
  await expect(navigation.getByRole('link', { name: 'Utilisateurs' })).toHaveCount(0)

  await page.goto('/orders')
  await expect(page.getByRole('link', { name: 'Nouvelle commande' })).toHaveCount(0)

  await page.goto('/users')
  await expect(page.getByRole('heading', { name: 'Accès refusé' })).toBeVisible()

  const token = await apiToken('viewer@ikea.test')
  expect((await apiGet(token, '/users')).status()).toBe(403)
  expect((await apiGet(token, '/integrations')).status()).toBe(403)
})
