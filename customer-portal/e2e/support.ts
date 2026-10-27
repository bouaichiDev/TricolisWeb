import { readFileSync } from 'node:fs'
import path from 'node:path'

import { expect, request, type Page } from '@playwright/test'

export const API = 'http://localhost:8002/api/v1/customer'

/** Comptes posés par `DemoCustomerPortalSeeder` (mot de passe de développement). */
export const PASSWORD = 'password'
export const IKEA_ADMIN = 'admin@ikea.test'
export const QOQA_ADMIN = 'admin@qoqa.test'

const LOG_FILE = path.resolve(import.meta.dirname, '../../storage/logs/laravel.log')

/**
 * Le jeton d'invitation du dernier courriel envoyé à cette adresse.
 *
 * En environnement de test, le mailer `log` écrit le message MIME brut dans
 * le journal Laravel. Le corps HTML y est en quoted-printable : les coupures
 * `=\n` et les `=3D` sont défaits avant de chercher le lien.
 */
export function invitationTokenFor(email: string): string {
  const raw = readFileSync(LOG_FILE, 'utf8')
  const decoded = raw.replace(/=\r?\n/g, '').replace(/=3D/g, '=')
  const start = decoded.lastIndexOf(`To: ${email}`)

  expect(start, `courriel d'invitation pour ${email}`).toBeGreaterThanOrEqual(0)

  const match = /activate-account\?token=([A-Za-z0-9]+)/.exec(decoded.slice(start))

  expect(match, 'lien d’activation dans le courriel').not.toBeNull()

  return (match as RegExpExecArray)[1]
}

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

/**
 * Attend la fin des notifications : en haut à droite, elles couvrent les
 * actions de l'en-tête, et le survol du pointeur suspend leur disparition.
 */
export async function dismissToasts(page: Page): Promise<void> {
  await page.mouse.move(0, 0)
  await expect(page.locator('[data-sonner-toast]')).toHaveCount(0, { timeout: 15_000 })
}

/** Un jeton de portail obtenu par l'API, pour les vérifications hors écran. */
export async function apiToken(email: string, password = PASSWORD): Promise<string> {
  const context = await request.newContext()
  const response = await context.post(`${API}/auth/login`, { data: { email, password } })

  expect(response.ok(), `connexion API de ${email} : ${response.status()} ${await response.text()}`).toBeTruthy()

  return (await response.json()).data.token as string
}

export async function apiGet(token: string, uri: string) {
  const context = await request.newContext()

  return context.get(`${API}${uri}`, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } })
}

/** Une écriture par l'API (PATCH, POST…), pour prouver un refus côté serveur. */
export async function apiSend(token: string, method: 'POST' | 'PATCH', uri: string, data: Record<string, unknown> = {}) {
  const context = await request.newContext()

  return context.fetch(`${API}${uri}`, { method, data, headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } })
}
