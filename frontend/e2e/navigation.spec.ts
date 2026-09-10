import { expect, test } from '@playwright/test'

import { heading, signIn, visit } from './support'

/**
 * Le §41 demande de vérifier chaque route par le clic **et** par l'URL directe.
 *
 * Une route qui ne vit qu'au travers du menu casse au rafraîchissement, sur un
 * lien partagé et sur le bouton « précédent ». Les trois se testent ici.
 */
test.describe('navigation', () => {
  test('ouvre les écrans principaux par leur URL directe', async ({ page }) => {
    await signIn(page)

    const routes: Array<[string, string | RegExp]> = [
      ['/orders', /Commandes/],
      ['/customers', /Clients/],
      ['/templates', /Modèles/],
      ['/communications/rules', /Règles automatiques/],
      ['/communications/history', /Historique des communications/],
      ['/billing/invoices', /Factures/],
      ['/stock', /[Ss]tock/],
    ]

    for (const [path, title] of routes) {
      await visit(page, path)
      await expect(heading(page, title).first()).toBeVisible()
    }
  })

  test('survit au rafraîchissement et au bouton précédent', async ({ page }) => {
    await signIn(page)

    await visit(page, '/templates')
    await page.reload()
    await expect(heading(page, /Modèles/).first()).toBeVisible()

    await visit(page, '/communications/rules')
    await page.goBack()

    await expect(page).toHaveURL(/\/templates/)
    await expect(heading(page, /Modèles/).first()).toBeVisible()
  })

  /**
   * Les accès aux modèles mènent tous au même écran, avec un filtre différent.
   * Le §0.15 de la Phase 9 interdit d'en faire plusieurs CRUD ; ce test le
   * vérifie là où c'est visible — dans le navigateur.
   *
   * Les deux premiers sont des portes du menu. Le troisième n'en est pas une :
   * le rayon des BL s'atteint par son URL, et c'est le lien que l'écran de
   * génération propose quand aucun modèle ne s'applique. Il doit donc ouvrir
   * cet écran-là, pas la liste complète.
   *
   * `templateType=invoice` est l'ancienne adresse de la porte comptable :
   * un signet posé dessus doit continuer d'ouvrir les modèles de facture.
   */
  test('les accès aux modèles mènent au même écran', async ({ page }) => {
    await signIn(page)

    await visit(page, '/templates?category=communication')
    await expect(heading(page, /^Modèles$/)).toBeVisible()

    await visit(page, '/templates?templateType=invoice')
    await expect(heading(page, /Modèles de facture/)).toBeVisible()

    await visit(page, '/templates?category=delivery_note')
    await expect(heading(page, /Modèles de bon de livraison/)).toBeVisible()
  })
})
