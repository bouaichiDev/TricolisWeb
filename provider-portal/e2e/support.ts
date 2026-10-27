import { expect, request, type Page } from '@playwright/test'

export const ROOT = 'http://localhost:8003/api/v1'
export const API = `${ROOT}/provider`

/** Comptes posés par `DemoProviderPortalSeeder` (mot de passe de développement). */
export const PASSWORD = 'password'
export const ALPHA_ADMIN = 'admin@transports-alpha.test'
export const BETA_ADMIN = 'admin@transports-beta.test'

export async function login(page: Page, email: string, password = PASSWORD): Promise<void> {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Mot de passe').fill(password)
  await page.getByRole('button', { name: 'Se connecter' }).click()
  await expect(page.getByRole('heading', { name: /Bonjour/ })).toBeVisible()
}

export async function logout(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Menu du compte' }).click()
  await page.getByRole('menuitem', { name: 'Se déconnecter' }).click()
  await expect(page.getByRole('heading', { name: /Connexion/ })).toBeVisible()
}

/** Un jeton du portail fournisseur obtenu par l'API, pour les vérifications hors écran. */
export async function apiToken(email: string, password = PASSWORD): Promise<string> {
  const context = await request.newContext()
  const response = await context.post(`${API}/auth/login`, { data: { email, password } })

  expect(response.ok(), `connexion API de ${email} : ${response.status()} ${await response.text()}`).toBeTruthy()

  return (await response.json()).data.token as string
}

/** Un appel authentifié à n'importe quelle route de l'API (interne, client, fournisseur). */
export async function apiGet(token: string, url: string, headers: Record<string, string> = {}) {
  const context = await request.newContext()

  return context.get(url, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json', ...headers } })
}
