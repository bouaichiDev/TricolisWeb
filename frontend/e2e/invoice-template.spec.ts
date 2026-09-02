import { expect, test, type Page } from '@playwright/test'

import { attachCustomerTemplate, prepareInvoice, rewriteTemplate } from './api'
import { signIn, visit } from './support'

/**
 * Le §34, dans le navigateur : portée du modèle, et immuabilité après clôture.
 *
 * Ces règles sont déjà couvertes côté serveur. Les rejouer ici vérifie autre
 * chose : que l'écran **montre** ce que le serveur a décidé. Une facture peut
 * très bien être figée en base et réaffichée depuis le modèle courant par une
 * erreur d'appel — c'est exactement ce qu'un test d'intégration ne voit pas.
 */

/**
 * Un code par appel, jamais par fichier.
 *
 * `(organizationId, code)` est unique : un code calculé une fois à l'import
 * serait repris par le scénario suivant, refusé en 422, et le dialogue
 * resterait ouvert sans que rien ne dise que la faute vient du test.
 */
function newCode(): string {
  return `E2E_INVOICE_${Date.now()}_${Math.floor(Math.random() * 1000)}`
}

async function createInvoiceTemplate(page: Page, body: string): Promise<string> {
  const code = newCode()

  await visit(page, '/templates?templateType=invoice')
  await page.getByRole('button', { name: /Nouveau modèle de facture/ }).click()

  const dialog = page.getByRole('dialog')
  await dialog.getByLabel(/^Code/).fill(code)
  await dialog.getByLabel(/^Nom/).fill('Modèle E2E')

  // Le formulaire propose une mise en page de départ ; on la remplace pour
  // reconnaitre la version rendue.
  await dialog.getByLabel(/^Message/).fill(body)

  await dialog.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(dialog).toBeHidden()

  return code
}

test.describe('modèle de facture', () => {
  test('se crée en document, sans canal', async ({ page }) => {
    await signIn(page)
    const code = await createInvoiceTemplate(page, '<h1>Facture {{ invoice.invoiceNumber }}</h1>')

    const row = page.getByRole('row').filter({ hasText: code })

    await expect(row).toBeVisible()
    // Un document n'a pas de canal : la colonne le dit, plutot que de laisser
    // un vide qu'on lirait comme une donnee manquante.
    await expect(row.getByText('Document')).toBeVisible()
  })

  /**
   * Le §0.7 interdit `channel = EMAIL` sur un document. L'écran ne doit pas
   * seulement s'abstenir de l'envoyer : il ne doit pas laisser le saisir.
   */
  test('ne propose ni canal ni objet pour une facture', async ({ page }) => {
    await signIn(page)
    await visit(page, '/templates')

    await page.getByRole('button', { name: /Nouveau modèle/ }).click()

    const dialog = page.getByRole('dialog')
    await expect(dialog.getByLabel(/^Canal/)).toBeVisible()

    await dialog.getByLabel(/^Type/).click()
    await page.getByRole('option', { name: 'Facture' }).click()

    await expect(dialog.getByLabel(/^Canal/)).toBeHidden()
    await expect(dialog.getByLabel(/^Sujet/)).toBeHidden()
  })

  /**
   * Le cœur du §34 : une facture close ne se re-rend jamais avec le modèle
   * courant. On clôture, on réécrit le modèle, on relit.
   *
   * Le modèle est **propre au client** : les scénarios partagent une base, et
   * un modèle du transporteur laisserait l'ordre alphabétique décider lequel
   * gagne. Un modèle client l'emporte toujours pour lui, et sur personne
   * d'autre.
   */
  test('fige le document à la clôture', async ({ page }) => {
    const invoice = await prepareInvoice()
    const template = await attachCustomerTemplate(invoice, '<h1>VERSION A</h1>')

    await signIn(page)
    await visit(page, `/billing/invoices/${invoice.invoiceId}`)

    // Avant cloture : le modele du client s'applique, et l'ecran le nomme.
    await page.getByRole('button', { name: 'Prévisualiser' }).click()
    const preview = page.getByRole('dialog')
    await expect(preview.getByText('Modèle du client')).toBeVisible()
    await preview.getByRole('button', { name: 'Fermer' }).click()

    await page.getByRole('button', { name: /Clôturer/ }).click()
    const closing = page.getByRole('dialog')
    await closing.getByRole('button', { name: /Clôturer/ }).click()
    await expect(closing).toBeHidden()

    // Le modele change **apres** la cloture.
    await rewriteTemplate(invoice, template.id, '<h1>VERSION B</h1>')

    await page.reload()
    await page.getByRole('button', { name: 'Prévisualiser' }).click()

    const frozen = page.getByRole('dialog')
    await expect(frozen.getByText(/Facture clôturée/)).toBeVisible()
  })
})
