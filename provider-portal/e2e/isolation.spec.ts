import { expect, test } from '@playwright/test'

import { ALPHA_ADMIN, API, BETA_ADMIN, ROOT, apiGet, apiToken } from './support'

/**
 * Un jeton du portail fournisseur n'ouvre que le portail fournisseur, et
 * seulement pour son fournisseur : jamais l'interne, jamais le portail client.
 */
test('le jeton d’Alpha est refusé par l’interne et par le portail client', async () => {
  const token = await apiToken(ALPHA_ADMIN)

  expect((await apiGet(token, `${ROOT}/providers`)).status()).toBe(403)
  expect((await apiGet(token, `${ROOT}/customer/context`)).status()).toBe(403)
})

test('chaque fournisseur ne voit que lui-même', async () => {
  const alpha = await (await apiGet(await apiToken(ALPHA_ADMIN), `${API}/auth/me`)).json()
  const beta = await (await apiGet(await apiToken(BETA_ADMIN), `${API}/auth/me`)).json()

  expect(alpha.data.provider.code).toBe('transports-alpha')
  expect(beta.data.provider.code).toBe('transports-beta')
})

test('aucune route de gestion de comptes n’existe sur le portail fournisseur', async () => {
  const token = await apiToken(ALPHA_ADMIN)

  for (const uri of ['/users', '/roles', '/users/invite']) {
    expect((await apiGet(token, `${API}${uri}`)).status(), uri).toBe(404)
  }
})
