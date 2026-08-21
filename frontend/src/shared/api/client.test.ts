import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { API, server } from '@/test/server'

import { api } from './client'

/** Capture l'URL réellement émise pour une route de test. */
function capture() {
  const urls: URL[] = []

  server.use(
    http.get(`${API}/statuses`, ({ request }) => {
      urls.push(new URL(request.url))

      return HttpResponse.json({ data: [], meta: {}, links: {} })
    }),
  )

  return urls
}

/**
 * Sérialisation des paramètres d'URL.
 *
 * La règle `boolean` de Laravel accepte `1`, `0`, `"1"` et `"0"` — **pas**
 * `"true"` ni `"false"`. Or c'est exactement ce que produit `String(true)` :
 * `?active=true` repartait en 422 et l'écran montrait une liste vide sans dire
 * pourquoi.
 */
describe('paramètres de requête', () => {
  it('envoie un booléen vrai sous la forme que Laravel accepte', async () => {
    const urls = capture()

    await api.get('/statuses', { query: { active: true } })

    expect(urls[0].searchParams.get('active')).toBe('1')
  })

  /** « Les inactifs » est un filtre : `false` doit partir, pas disparaître. */
  it('envoie un booléen faux plutôt que de l’omettre', async () => {
    const urls = capture()

    await api.get('/statuses', { query: { active: false } })

    expect(urls[0].searchParams.get('active')).toBe('0')
  })

  it('omet ce qui est absent ou vide', async () => {
    const urls = capture()

    await api.get('/statuses', {
      query: { search: '', source: undefined, status: null, page: 1 },
    })

    expect(urls[0].searchParams.has('search')).toBe(false)
    expect(urls[0].searchParams.has('source')).toBe(false)
    expect(urls[0].searchParams.has('status')).toBe(false)
    expect(urls[0].searchParams.get('page')).toBe('1')
  })
})
