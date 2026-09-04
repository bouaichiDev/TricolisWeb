import { expect, test } from '@playwright/test'

import { heading, signIn, visit } from './support'

/**
 * Le tableau de bord par rôle, dans un vrai navigateur.
 *
 * **Ce que ce parcours ne couvre pas, et pourquoi.** Le scénario complet —
 * régler un rôle, puis se connecter avec un compte qui le porte pour constater
 * ce qu'il voit — demande un compte porteur de ce rôle, et le seul compte
 * qu'un poste de développement sème est le **propriétaire** de l'organisation :
 * il détient tout sans passer par un rôle, et n'en porte aucun. Le fabriquer
 * ici par l'interface — créer un rôle, créer un membre, lui poser un mot de
 * passe, se reconnecter — aurait fait de ce fichier un test de la gestion des
 * membres.
 *
 * Ces combinaisons se vérifient donc là où elles se construisent vraiment, en
 * une ligne : `tests/Feature/Api/V1/Dashboard/DashboardTest.php` couvre le rôle
 * unique, le cumul de rôles, l'intersection des permissions, leur retrait et
 * l'isolation entre organisations — y compris l'absence du chiffre dans le
 * corps de la réponse, qu'aucun test de navigateur ne saurait constater.
 *
 * Restent ici les deux choses qu'un navigateur seul peut dire : que l'écran de
 * réglage enregistre réellement, et que le tableau de bord se rend.
 */
test.describe('tableau de bord par rôle', () => {
  test('règle le tableau de bord d’un rôle, et le retrouve après rechargement', async ({
    page,
  }) => {
    await signIn(page)
    await visit(page, '/roles')

    await page.getByRole('link', { name: 'Administrateur' }).first().click()
    await expect(heading(page, 'Administrateur').first()).toBeVisible()

    await page.getByRole('tab', { name: 'Tableau de bord' }).click()

    const widget = page.getByRole('switch', { name: 'Réclamations ouvertes' })
    await expect(widget).toBeVisible()

    const wasEnabled = (await widget.getAttribute('data-state')) === 'checked'
    await widget.click()

    await page.getByRole('button', { name: 'Enregistrer la configuration' }).click()
    await expect(page.getByText('Configuration du tableau de bord enregistrée.')).toBeVisible()

    // Le rechargement est le cœur du test : un brouillon qui n'aurait pas
    // atteint la base se relirait pourtant à l'écran tant qu'on n'y revient
    // pas.
    await page.reload()
    await page.getByRole('tab', { name: 'Tableau de bord' }).click()

    await expect(page.getByRole('switch', { name: 'Réclamations ouvertes' })).toHaveAttribute(
      'data-state',
      wasEnabled ? 'unchecked' : 'checked',
    )
  })

  /**
   * L'écran de réglage annonce ce qui manque plutôt que de le masquer. Le rôle
   * `admin` porte toutes les permissions de l'organisation : c'est donc
   * l'absence de ce message qu'on vérifie ici, et sa présence est tenue par
   * `RoleDashboardPanel.test.tsx`, où le catalogue se compose.
   */
  test('n’annonce aucune permission manquante sur un rôle qui les détient toutes', async ({
    page,
  }) => {
    await signIn(page)
    await visit(page, '/roles')

    await page.getByRole('link', { name: 'Administrateur' }).first().click()
    await page.getByRole('tab', { name: 'Tableau de bord' }).click()

    await expect(page.getByText('Widgets disponibles')).toBeVisible()
    await expect(page.getByText(/^Permission requise :/)).toHaveCount(0)
  })

  test('rend le tableau de bord, et survit au rechargement', async ({ page }) => {
    await signIn(page)
    await visit(page, '/dashboard')

    // Le propriétaire ne porte aucun rôle : il reçoit donc les widgets par
    // défaut du catalogue, tous autorisés puisqu'il détient tout.
    await expect(page.getByText('Clients').first()).toBeVisible()

    await page.reload()
    await expect(page.getByText('Clients').first()).toBeVisible()
  })
})
