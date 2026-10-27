/**
 * Jeton du portail fournisseur.
 *
 * Gardé en `sessionStorage`, pas en `localStorage` : il meurt avec l'onglet.
 * Un poste partagé dans un dépôt ou une cabine ne garde pas la session du
 * fournisseur une fois la fenêtre fermée ; le prix est une reconnexion par
 * onglet, assumé.
 *
 * Sa clé est distincte de celles du back-office et du portail client : servis
 * sur la même machine en développement, ils ne relisent jamais le jeton l'un
 * de l'autre. Le backend les refuserait de toute façon.
 */
const TOKEN_KEY = 'tricolis.provider-portal.token'

type Listener = () => void

const listeners = new Set<Listener>()

function read(): string | null {
  try {
    return sessionStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export const session = {
  getToken: read,

  setToken(token: string): void {
    try {
      sessionStorage.setItem(TOKEN_KEY, token)
    } catch {
      // Stockage indisponible (navigation privée stricte) : la session ne
      // survivra pas au rechargement.
    }
  },

  clear(): void {
    try {
      sessionStorage.removeItem(TOKEN_KEY)
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
