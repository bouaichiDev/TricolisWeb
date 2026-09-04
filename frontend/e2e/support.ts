import { expect, type Page } from '@playwright/test'

/**
 * Identifiants semés par `DevelopmentOrganizationSeeder`.
 *
 * Ils ne sont ni inventés, ni secrets : ce sont ceux d'une base de test semée,
 * que `config('tricolis.development_password')` fixe à `password` faute de
 * `DEV_ADMIN_PASSWORD`. Aucun identifiant réel n'entre dans ce dépôt.
 */
export const ADMIN = {
  email: 'admin@tricolis.dev',
  password: 'password',
}

/**
 * La barre latérale, nommée.
 *
 * Deux `<nav>` coexistent — le menu et le fil d'Ariane — et viser le rôle seul
 * échoue en mode strict. Le nom accessible les distingue, ce qui vérifie au
 * passage qu'il existe.
 */
export function mainNav(page: Page) {
  return page.getByRole('navigation', { name: 'Navigation principale' })
}

/**
 * Ouvre une session et attend que l'application soit réellement prête.
 *
 * Attendre l'URL ne suffit pas : le routeur redirige avant que `/auth/me` n'ait
 * répondu, et un test qui cliquerait aussitôt viserait une barre latérale
 * encore vide. On attend donc un élément que seule une session établie affiche.
 */
export async function signIn(page: Page): Promise<void> {
  await page.goto('/login')

  await page.getByLabel('Adresse e-mail').fill(ADMIN.email)
  await page.getByLabel('Mot de passe').fill(ADMIN.password)
  await page.getByRole('button', { name: 'Se connecter' }).click()

  await expect(mainNav(page)).toBeVisible({ timeout: 20_000 })
}

/**
 * Ouvre une page par son URL, session déjà établie.
 *
 * Le §41 demande de vérifier l'accès direct autant que le clic depuis le menu :
 * une route qui n'existe qu'au travers du menu casse au rafraîchissement et sur
 * un lien partagé.
 */
export async function visit(page: Page, path: string): Promise<void> {
  await page.goto(path)
  await expect(mainNav(page)).toBeVisible()
}

/** Le titre de page, tel que `PageHeader` le rend. */
export function heading(page: Page, name: string | RegExp) {
  return page.getByRole('heading', { name })
}
