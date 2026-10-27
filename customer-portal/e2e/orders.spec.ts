import { expect, test, type Page } from '@playwright/test'

import { dismissToasts, login } from './support'

/**
 * Scénario de la Phase 2 (§25) :
 *
 * Login IKEA → Commandes → Création (catalogue + ligne libre, colis, service,
 * adresse, contact, document)
 * → Validation → Détail → Modification → Duplication → Annulation.
 */
async function next(page: Page) {
  await page.getByRole('button', { name: 'Suivant' }).click()
}

test('an IKEA order manager runs an order through its whole life', async ({ page }) => {
  const reference = `E2E-${Date.now()}`
  const date = new Date(Date.now() + 5 * 86_400_000).toISOString().slice(0, 10)

  await login(page, 'orders@ikea.test')
  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Commandes' }).click()
  await page.getByRole('link', { name: 'Nouvelle commande' }).click()

  // 1. Informations
  await page.getByLabel('Votre référence').fill(reference)
  await next(page)

  // 2. Lignes : deux articles du catalogue IKEA d'un coup, plus une ligne libre
  await page.getByRole('button', { name: 'Parcourir le catalogue' }).click()
  const catalog = page.getByRole('dialog')
  await catalog.getByRole('checkbox', { name: /Billy/ }).check()
  await catalog.getByLabel('Quantité de Bibliothèque Billy 80 cm').fill('2')
  await catalog.getByRole('checkbox', { name: /Kallax/ }).check()
  await catalog.getByRole('button', { name: 'Ajouter 2 articles' }).click()
  await page.getByRole('button', { name: 'Ajouter une ligne libre' }).click()
  await page.getByLabel('Désignation').fill('Pieds de lit')
  await next(page)

  // 3. Colis : un colis qui contient la ligne
  await page.getByRole('button', { name: 'Ajouter un colis' }).click()
  await page.getByRole('button', { name: 'Mettre une ligne dans ce colis' }).click()
  await page.getByLabel('Ligne').selectOption({ index: 1 })
  await next(page)

  // 4. Service : livraison à l'entrepôt du carnet, avec un contact
  await page.getByLabel('Service').selectOption({ label: 'Livraison' })
  await page.getByLabel('Date souhaitée').fill(date)
  await page.getByLabel('Adresse', { exact: true }).selectOption({ index: 1 })
  await page.getByRole('button', { name: 'Ajouter un contact' }).click()
  await page.getByLabel('Prénom').fill('Anna')
  await page.getByLabel('Colis 1').check()
  await next(page)

  // 5. Documents : un bon joint dès la création
  await page.getByLabel('Documents à joindre').setInputFiles({ name: 'bon-commande.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 e2e') })
  await expect(page.getByText('bon-commande.pdf')).toBeVisible()
  await next(page)

  // 6. Récapitulatif puis validation
  await expect(page.getByText(reference)).toBeVisible()
  await page.getByRole('button', { name: 'Valider la commande' }).click()
  await expect(page.getByText(`Votre référence : ${reference}`)).toBeVisible()

  // Détail : services, colis, articles
  await page.getByRole('tab', { name: /Services/ }).click()
  await expect(page.getByText('Anna')).toBeVisible()
  await page.getByRole('tab', { name: /Articles/ }).click()
  await expect(page.getByRole('cell', { name: 'BILLY-80' })).toBeVisible()
  await expect(page.getByRole('cell', { name: 'Pieds de lit' })).toBeVisible()
  await page.getByRole('tab', { name: 'Documents' }).click()
  await expect(page.getByText('bon-commande.pdf')).toBeVisible()

  // Modification de la référence
  await page.getByRole('link', { name: 'Modifier' }).click()
  await page.getByLabel('Votre référence').fill(`${reference}-B`)
  for (let step = 0; step < 5; step++) await next(page)
  await page.getByRole('button', { name: 'Enregistrer les modifications' }).click()
  await expect(page.getByText(`Votre référence : ${reference}-B`)).toBeVisible()

  // Duplication : redirection vers la nouvelle commande
  const original = page.url()
  page.once('dialog', (dialog) => void dialog.accept())
  await page.getByRole('button', { name: 'Dupliquer' }).click()
  await expect(page).not.toHaveURL(original)
  await expect(page.getByText(/créée par duplication/)).toBeVisible()

  // Annulation de la copie, avec motif
  await dismissToasts(page)
  await page.getByRole('button', { name: 'Annuler' }).click()
  await page.getByLabel('Motif').fill('Doublon de test')
  await page.getByRole('button', { name: 'Confirmer l’annulation' }).click()
  await expect(page.getByText('Annulée').first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Annuler' })).toHaveCount(0)
})
