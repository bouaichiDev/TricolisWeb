import path from 'node:path'

import { expect, test } from '@playwright/test'

import { IKEA_ADMIN, login, QOQA_ADMIN } from './support'

const FIXTURE = path.resolve(import.meta.dirname, '../../tests/Fixtures/integrations/orders.xlsx')

/**
 * Scénarios de la Phase 6 sur `DemoCustomerPortalIntegrationsSeeder` : QOQA
 * dépose un classeur Excel (une commande valide, une sans quantité) ; IKEA
 * voit sa relève XML automatique, sans aucun moyen d'y déposer un fichier.
 */
test('a QOQA admin previews an Excel file, then imports the valid order', async ({ page }) => {
  await login(page, QOQA_ADMIN)
  await page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Intégrations' }).click()

  await page.getByRole('button', { name: /Importer un fichier/ }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Fichier').setInputFiles(FIXTURE)
  await dialog.getByRole('button', { name: 'Prévisualiser' }).click()

  await expect(dialog.getByLabel('Commandes', { exact: true }).getByText('XL-1001')).toBeVisible()
  await expect(dialog.getByText(/lines\.0\.quantity/)).toBeVisible()

  await dialog.getByRole('button', { name: /Importer \d+ commande/ }).click()
  // Relancé sur une base déjà importée, la commande est reconnue et ignorée.
  await expect(dialog.getByRole('region', { name: 'Résultat' })).toBeVisible()
  await expect(dialog.getByText(/XL-1002 — Refusée/)).toBeVisible()
})

test('an IKEA admin sees its automatic XML flow without any upload action', async ({ page }) => {
  await login(page, IKEA_ADMIN)
  await page.goto('/integrations')

  await expect(page.getByText('Commandes XML (SFTP)')).toBeVisible()
  await expect(page.getByText('SFTP (relève automatique)')).toBeVisible()
  await expect(page.getByText('Commandes Excel')).toHaveCount(0)
  await expect(page.getByRole('button', { name: /Importer un fichier/ })).toHaveCount(0)
})
