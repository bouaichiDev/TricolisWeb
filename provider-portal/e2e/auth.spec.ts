import { expect, test } from '@playwright/test'

import { ALPHA_ADMIN, login, logout } from './support'

/**
 * Parcours de base : connexion → tableau de bord → profil → déconnexion.
 * Le menu ne propose jamais de gestion de comptes ni de rôles.
 */
test('un fournisseur se connecte, voit ses chiffres et son profil, puis se déconnecte', async ({ page }) => {
  await login(page, ALPHA_ADMIN)

  await expect(page.getByTestId('current-provider')).toHaveText('Transports Alpha')
  await expect(page.getByText('Tournées du jour')).toBeVisible()
  await expect(page.getByText('Chauffeurs actifs')).toBeVisible()

  const nav = page.getByRole('navigation', { name: 'Navigation principale' })
  await expect(nav.getByRole('link', { name: 'Tableau de bord' })).toBeVisible()
  await expect(nav.getByText('Utilisateurs')).toHaveCount(0)
  await expect(nav.getByText('Rôles')).toHaveCount(0)

  await nav.getByRole('link', { name: 'Profil' }).click()
  await expect(page.getByRole('heading', { name: 'Mon profil' })).toBeVisible()
  await expect(page.getByText('transports-alpha', { exact: true })).toBeVisible()

  // Le jeton vit le temps de l'onglet, jamais dans le stockage persistant.
  expect(await page.evaluate(() => sessionStorage.getItem('tricolis.provider-portal.token'))).not.toBeNull()
  expect(await page.evaluate(() => localStorage.length)).toBe(0)

  await logout(page)
  expect(await page.evaluate(() => sessionStorage.getItem('tricolis.provider-portal.token'))).toBeNull()
})

test('un mauvais mot de passe est refusé sans ouvrir de session', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Email').fill(ALPHA_ADMIN)
  await page.getByLabel('Mot de passe').fill('mauvais-mot-de-passe')
  await page.getByRole('button', { name: 'Se connecter' }).click()

  await expect(page.getByRole('alert')).toContainText('Adresse e-mail ou mot de passe incorrect.')
  expect(await page.evaluate(() => sessionStorage.getItem('tricolis.provider-portal.token'))).toBeNull()
})
