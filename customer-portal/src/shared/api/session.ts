/**
 * Jeton du portail client.
 *
 * Sa clé de stockage est distincte de celle du back-office : servis sur la
 * même machine en développement, les deux frontends ne doivent jamais
 * relire le jeton l'un de l'autre. Le backend les refuserait de toute façon
 * (un jeton de portail n'ouvre pas l'interne, et inversement).
 */
const TOKEN_KEY = 'tricolis.customer-portal.token'

type Listener = () => void

const listeners = new Set<Listener>()

function read(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export const session = {
  getToken: read,

  setToken(token: string): void {
    try {
      localStorage.setItem(TOKEN_KEY, token)
    } catch {
      // Stockage indisponible (navigation privée stricte) : la session vivra
      // le temps de l'onglet.
    }
  },

  clear(): void {
    try {
      localStorage.removeItem(TOKEN_KEY)
    } catch {
      // Rien à effacer.
    }

    listeners.forEach((listener) => listener())
  },

  /** Prévient l'`AuthProvider` quand le client HTTP abandonne la session. */
  onClear(listener: Listener): () => void {
    listeners.add(listener)

    return () => listeners.delete(listener)
  },
}
