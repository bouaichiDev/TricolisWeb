import { afterEach, describe, expect, it, vi } from 'vitest'

/** Le module de formatage, rechargé dans le fuseau demandé. */
async function formatIn(timeZone: string) {
  vi.stubEnv('TZ', timeZone)
  vi.resetModules()

  return import('./format')
}

describe('dates du portail selon le fuseau du navigateur', () => {
  afterEach(() => {
    vi.unstubAllEnvs()
    vi.resetModules()
  })

  it('garde le jour d’une date seule à l’ouest de Greenwich', async () => {
    const { formatDate } = await formatIn('America/New_York')

    // Le piège existe bien dans ce fuseau : sans précaution, on recule d'un jour.
    expect(new Date('2026-10-12').getDate()).toBe(11)
    expect(formatDate('2026-10-12')).toContain('12')
  })

  it('garde le jour d’une date seule à l’est de Greenwich', async () => {
    const { formatDate } = await formatIn('Europe/Zurich')

    expect(formatDate('2026-10-12')).toContain('12')
  })

  it('convertit un horodatage UTC dans l’heure locale', async () => {
    const { formatDateTime } = await formatIn('Europe/Zurich')

    // 15:30 UTC = 17:30 à Zurich en heure d'été.
    expect(formatDateTime('2026-10-12T15:30:00.000000Z')).toContain('17:30')
  })
})
