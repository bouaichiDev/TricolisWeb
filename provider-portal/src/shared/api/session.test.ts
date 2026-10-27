import { session } from './session'

const KEY = 'tricolis.provider-portal.token'

describe('session du portail fournisseur', () => {
  beforeEach(() => {
    sessionStorage.clear()
    localStorage.clear()
  })

  it('garde le jeton le temps de l’onglet, jamais dans le stockage persistant', () => {
    session.setToken('abc')

    expect(sessionStorage.getItem(KEY)).toBe('abc')
    expect(localStorage.length).toBe(0)
    expect(session.getToken()).toBe('abc')
  })

  it('ignore le jeton du portail client et du back-office', () => {
    localStorage.setItem('tricolis.customer-portal.token', 'client')
    sessionStorage.setItem('tricolis.customer-portal.token', 'client')

    expect(session.getToken()).toBeNull()
  })

  it('efface le jeton et prévient les abonnés', () => {
    const listener = vi.fn()
    const unsubscribe = session.onClear(listener)
    session.setToken('abc')

    session.clear()

    expect(sessionStorage.getItem(KEY)).toBeNull()
    expect(listener).toHaveBeenCalledTimes(1)
    unsubscribe()
  })
})
