import { expect, test } from '@playwright/test'

import { apiGet, apiToken, IKEA_ADMIN, login, QOQA_ADMIN } from './support'

/**
 * Isolation multi-client de bout en bout (Phase 8, §19) : IKEA ne voit ni ne
 * reçoit rien de QOQA — ni dans ses listes, ni par URL directe, ni par l'API.
 */
test('IKEA never reaches a QOQA order, stock item or integration', async ({ page }) => {
  const qoqa = await apiToken(QOQA_ADMIN)
  const qoqaOrder = (await (await apiGet(qoqa, '/orders?perPage=1')).json()).data[0] as { id: string; reference: string }
  const qoqaStock = ((await (await apiGet(qoqa, '/stock/items?perPage=1')).json()).data[0] ?? null) as { id: string } | null
  const qoqaImport = ((await (await apiGet(qoqa, '/integrations/import-configurations')).json()).data[0] ?? null) as { id: string; name: string } | null

  // Écran : la liste d'IKEA ne porte pas la commande de QOQA, l'URL directe non plus.
  await login(page, IKEA_ADMIN)
  await page.goto('/orders')
  await expect(page.getByRole('table')).toBeVisible()
  await expect(page.getByText(qoqaOrder.reference)).toHaveCount(0)
  await page.goto(`/orders/${qoqaOrder.id}`)
  await expect(page.getByText('Commande introuvable')).toBeVisible()

  // API : 404 générique, comme pour un identifiant qui n'existe pas.
  const ikea = await apiToken(IKEA_ADMIN)
  expect((await apiGet(ikea, `/orders/${qoqaOrder.id}`)).status()).toBe(404)
  expect((await apiGet(ikea, `/orders?customerId=${qoqaOrder.id}`)).status()).toBe(422)

  if (qoqaStock) {
    expect((await apiGet(ikea, `/stock/items/${qoqaStock.id}`)).status()).toBe(404)
  }

  if (qoqaImport) {
    const imports = (await (await apiGet(ikea, '/integrations/import-configurations')).json()).data as { id: string }[]
    expect(imports.map((item) => item.id)).not.toContain(qoqaImport.id)
  }
})
