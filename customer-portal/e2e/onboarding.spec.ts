import { expect, test } from '@playwright/test'

import { IKEA_ADMIN, QOQA_ADMIN, apiGet, apiToken, invitationTokenFor, login, logout } from './support'

/**
 * Scénario de la Phase 1 (§18, étape 9) :
 *
 * Customer Admin IKEA → login → invite → activation → login du nouvel
 * utilisateur → permissions correctes → tentative d'accès à QOQA → refus.
 */
test('an IKEA admin onboards a viewer who never reaches QOQA', async ({ page }) => {
  const email = `lecteur-${Date.now()}@ikea.test`
  const password = 'Motdepasse-E2E-2026!'

  // 1. L'administrateur IKEA se connecte et invite un lecteur.
  await login(page, IKEA_ADMIN)
  await expect(page.getByTestId('current-customer')).toHaveText('IKEA')

  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Utilisateurs' }).click()
  await page.getByRole('button', { name: 'Inviter' }).click()

  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Email').fill(email)
  await dialog.getByLabel('Rôle').selectOption({ label: 'Lecteur' })
  await dialog.getByRole('button', { name: 'Envoyer l’invitation' }).click()
  await expect(page.getByText(`Invitation envoyée à ${email}.`)).toBeVisible()
  await expect(page.getByRole('link', { name: email })).toBeVisible()

  await logout(page)

  // 2. Le lecteur active son compte depuis le lien reçu.
  await page.goto(`/activate-account?token=${invitationTokenFor(email)}`)
  await expect(page.getByRole('heading', { name: 'Rejoindre IKEA' })).toBeVisible()
  await page.getByLabel('Prénom').fill('Lina')
  await page.getByLabel('Nom', { exact: true }).fill('Lecture')
  await page.getByLabel('Mot de passe', { exact: true }).fill(password)
  await page.getByLabel('Confirmation').fill(password)
  await page.getByRole('button', { name: 'Activer mon accès' }).click()
  await expect(page.getByText('Votre compte est activé.')).toBeVisible()

  // 3. Il se connecte : IKEA, lecture seule, pas d'administration.
  await login(page, email, password)
  await expect(page.getByTestId('current-customer')).toHaveText('IKEA')

  const nav = page.getByRole('navigation', { name: 'Navigation principale' })
  await expect(nav.getByText('Commandes')).toBeVisible()
  await expect(nav.getByText('Utilisateurs')).toHaveCount(0)

  await page.goto('/users')
  await expect(page.getByRole('heading', { name: 'Accès refusé' })).toBeVisible()

  // 4. QOQA reste hors d'atteinte, quel que soit l'identifiant tenté.
  const qoqaToken = await apiToken(QOQA_ADMIN)
  const qoqaMembers = (await (await apiGet(qoqaToken, '/users')).json()).data as { id: string }[]
  const qoqaMemberId = qoqaMembers[0].id

  const viewerToken = await apiToken(email, password)
  expect((await apiGet(viewerToken, `/users/${qoqaMemberId}`)).status()).toBe(403)
  expect((await (await apiGet(viewerToken, '/context')).json()).data.customer.code).toBe('ikea')

  const ikeaAdminToken = await apiToken(IKEA_ADMIN)
  expect((await apiGet(ikeaAdminToken, `/users/${qoqaMemberId}`)).status()).toBe(404)

  await logout(page)
  await login(page, IKEA_ADMIN)
  await page.goto(`/users/${qoqaMemberId}`)
  await expect(page.getByText('Utilisateur introuvable')).toBeVisible()
})
