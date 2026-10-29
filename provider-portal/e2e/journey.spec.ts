import { expect, test, type Page } from '@playwright/test'

import { ALPHA_ADMIN, API, BETA_ADMIN, apiGet, apiToken, login } from './support'

/** Un PNG réel d'un pixel : le serveur lit le contenu, pas l'extension. */
const SIGNATURE = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64')

const nav = (page: Page) => page.getByRole('navigation', { name: 'Navigation principale' })

/**
 * Phase 8 — le parcours complet d'Alpha sur DEMO-ALPHA-4, une tournée confiée
 * par le transporteur et encore sans ressource : ajouter un chauffeur et un
 * véhicule, les affecter, démarrer, arriver, prouver la livraison, terminer.
 * Préalable : `DemoProviderPortalTourSeeder` (remet DEMO-ALPHA-4 à zéro).
 */
test('Alpha mène une tournée de bout en bout avec une flotte qu’il vient de créer', async ({ page }) => {
  const stamp = Date.now().toString().slice(-6)
  await login(page, ALPHA_ADMIN)
  await expect(page.getByText('Tournées du jour')).toBeVisible()

  await nav(page).getByRole('link', { name: 'Chauffeurs' }).click()
  await page.getByRole('button', { name: 'Ajouter un chauffeur' }).click()
  const driver = page.getByRole('dialog')
  await driver.getByLabel('Code').fill(`J-D${stamp}`)
  await driver.getByLabel('Prénom').fill('Jules')
  await driver.getByLabel('Nom', { exact: true }).fill(`Parcours${stamp}`)
  await driver.getByLabel('E-mail').fill(`jules.${stamp}@alpha.test`)
  await driver.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(page.getByRole('cell', { name: `J-D${stamp}` })).toBeVisible()

  await nav(page).getByRole('link', { name: 'Véhicules' }).click()
  await page.getByRole('button', { name: 'Ajouter un véhicule' }).click()
  const vehicle = page.getByRole('dialog')
  await vehicle.getByLabel('Code').fill(`J-V${stamp}`)
  await vehicle.getByLabel('Immatriculation').fill(`J-${stamp}`)
  await vehicle.getByLabel('Type de véhicule').selectOption({ index: 1 })
  await vehicle.getByLabel('Charge utile (kg)').fill('2000')
  await vehicle.getByLabel('Volume (m³)').fill('20')
  await vehicle.getByLabel('Palettes').fill('6')
  await vehicle.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(page.getByRole('row').filter({ hasText: `J-V${stamp}` })).toBeVisible()

  await nav(page).getByRole('link', { name: 'Tournées' }).click()
  await page.getByRole('link', { name: 'DEMO-ALPHA-4' }).click()
  await expect(page.getByRole('heading', { name: 'Tournée DEMO-ALPHA-4' })).toBeVisible()

  // Tournée multi-clients : le service et le lieu sont là ; la commande, le
  // code client et toute donnée commerciale non.
  await expect(page.getByText('S2').first()).toBeVisible()
  await expect(page.locator('main')).not.toContainText(/PROVIDER-DEMO-1|demo-client|prix|tarif|facture/i)

  await page.getByRole('button', { name: 'Affecter' }).click()
  const assign = page.getByRole('dialog')
  await assign.getByLabel('Chauffeur').selectOption({ label: `Jules Parcours${stamp} (J-D${stamp})` })
  await assign.getByLabel('Véhicule').selectOption({ label: `J-V${stamp} · J-${stamp}` })
  await assign.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(assign).toBeHidden()

  await page.getByRole('button', { name: 'Démarrer' }).click()
  await page.getByRole('button', { name: 'Arrivé' }).click()

  await page.getByRole('button', { name: 'Preuve de livraison' }).click()
  const proof = page.getByRole('dialog')
  await proof.getByLabel('Réceptionnaire').fill('Réception Parcours')
  await proof.getByLabel('Signature (image)').setInputFiles({ name: 'signature.png', mimeType: 'image/png', buffer: SIGNATURE })
  await proof.getByRole('button', { name: 'Enregistrer la preuve' }).click()
  await expect(page.getByText('Réception Parcours')).toBeVisible()

  await page.getByRole('button', { name: 'Terminé', exact: true }).click()
  await page.getByRole('button', { name: 'Terminer la tournée' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Terminer' }).click()
  await expect(page.getByText('Terminée').first()).toBeVisible()

  await page.getByRole('tab', { name: 'Chronologie' }).click()
  await expect(page.getByRole('list', { name: 'Chronologie' }).getByText('Tournée terminée')).toBeVisible()
})

test('Beta ne lit ni la tournée, ni les documents, ni les décomptes d’Alpha', async () => {
  const alpha = await apiToken(ALPHA_ADMIN)
  const beta = await apiToken(BETA_ADMIN)
  const tour = (await (await apiGet(alpha, `${API}/tours?search=DEMO-ALPHA-3&state=running`)).json()).data[0].id as string
  const document = (await (await apiGet(alpha, `${API}/tours/${tour}/documents`)).json()).data[0]?.id as string | undefined
  const settlement = (await (await apiGet(alpha, `${API}/settlements`)).json()).data[0].id as string

  for (const url of [`/tours/${tour}`, `/tours/${tour}/tracking`, `/tours/${tour}/documents`, `/settlements/${settlement}`, `/settlements/${settlement}/documents`]) {
    expect((await apiGet(beta, `${API}${url}`)).status(), url).toBe(404)
  }
  if (document !== undefined) {
    expect((await apiGet(beta, `${API}/documents/${document}`)).status()).toBe(404)
  }
})
