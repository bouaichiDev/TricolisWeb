import { expect, test, type Page } from '@playwright/test'

import { prepareDeliveryNote } from './api'
import { signIn, visit } from './support'

/**
 * Le parcours complet du bon de livraison, dans le navigateur.
 *
 * Créer un modèle dans « Modèles », ouvrir une commande, aller à ses services,
 * générer le BL — et vérifier que le document produit est **celui de ce
 * modèle**, avec les seuls colis et articles de ce service.
 *
 * Les règles de résolution et de périmètre sont déjà couvertes côté serveur.
 * Les rejouer ici vérifie autre chose : que les deux écrans se rejoignent
 * réellement. Un modèle peut être parfaitement enregistré et l'écran de
 * génération continuer d'appeler autre chose — c'est exactement ce qu'un test
 * d'intégration ne voit pas.
 */

/** Un code par exécution : la base de test est partagée entre scénarios. */
function templateCode(): string {
  return `E2E_BL_${Date.now()}_${Math.floor(Math.random() * 1000)}`
}

/**
 * Le corps du modèle, réduit à ce que le test doit reconnaître.
 *
 * La mise en page proposée par l'écran est remplacée : elle est juste, mais
 * elle contient tant de texte qu'un marqueur s'y perdrait. Les trois chemins
 * employés figurent déjà dans les variables déclarées par cette mise en page,
 * qu'on laisse en place.
 */
function body(marker: string): string {
  return [
    `<h1>${marker} {{ deliveryNote.number }}</h1>`,
    '<p>{{ orderer.name }}</p>',
    '{{#packages}}<p>COLIS {{ packages.reference }}</p>{{/packages}}',
    '{{#articles}}<p>ARTICLE {{ articles.name }} x {{ articles.quantity }}</p>{{/articles}}',
  ].join('')
}

/** Le dialogue de génération, ouvert depuis le panneau d'un service. */
async function openDeliveryNote(page: Page): Promise<void> {
  await page.getByRole('tab', { name: 'Services' }).click()
  await page.getByRole('button', { name: 'Détail' }).first().click()
  await page.getByRole('button', { name: 'Générer le BL' }).click()
}

test('crée un modèle de BL puis génère le bon depuis un service', async ({ page }) => {
  const prepared = await prepareDeliveryNote()
  const code = templateCode()
  const marker = 'MODELE-E2E-BL'

  await signIn(page)

  // 1. La configuration, dans « Modèles ».
  await visit(page, '/templates?category=delivery_note')

  await page.getByRole('button', { name: 'Nouveau modèle de BL' }).click()

  const dialog = page.getByRole('dialog')
  await dialog.getByLabel(/^Code/).fill(code)
  await dialog.getByLabel(/^Nom/).fill('Modèle BL E2E')

  // La prestation rend le modèle plus précis que tout modèle générique : sur
  // une base partagée, c'est ce qui garantit que c'est bien lui qui servira.
  await dialog.getByLabel(/^Prestation/).click()
  await page.getByRole('option', { name: prepared.serviceName }).click()

  await dialog.getByLabel(/^Message/).fill(body(marker))
  await dialog.getByRole('button', { name: 'Enregistrer' }).click()

  await expect(dialog).toBeHidden()
  await expect(page.getByText(code)).toBeVisible()

  // 2. La génération, depuis « Détail commande → Services ».
  await visit(page, `/orders/${prepared.orderId}`)
  await openDeliveryNote(page)

  const preview = page.frameLocator('iframe[title="Bon de livraison"]')

  await expect(preview.getByText(marker)).toBeVisible()
  await expect(preview.getByText(`BL-${prepared.orderNumber}-SRV-1`)).toBeVisible()
  await expect(preview.getByText(`COLIS ${prepared.packageReference}`)).toBeVisible()

  // La quantité est celle rangée dans le colis — quatre — non les dix
  // commandées : c'est ce que le destinataire reçoit.
  await expect(preview.getByText(`ARTICLE ${prepared.articleName} x 4.000`)).toBeVisible()

  // L'impression appelle `print()` **sur le cadre**. Sans origine commune, cet
  // accès lèverait une erreur de sécurité — d'où cette vérification, qui n'ouvre
  // aucune boîte de dialogue.
  const printable = await page.evaluate(() => {
    const frame = document.querySelector('iframe[title="Bon de livraison"]')

    return typeof (frame as HTMLIFrameElement).contentWindow?.print === 'function'
  })
  expect(printable, 'le cadre de l’aperçu est imprimable').toBe(true)

  // 3. Le PDF, enregistré dans les documents de la commande.
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Générer et télécharger' }).click()

  expect((await download).suggestedFilename()).toBe(`BL-${prepared.orderNumber}-SRV-1.pdf`)

  await page.getByRole('button', { name: 'Fermer' }).click()
  await page.getByRole('tab', { name: 'Documents' }).click()

  await expect(page.getByText(`BL-${prepared.orderNumber}-SRV-1.pdf`)).toBeVisible()
})
