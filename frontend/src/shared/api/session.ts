/**
 * Jeton et organisation active, persistés entre deux chargements.
 *
 * Deux raisons de passer par ce module plutôt que par `localStorage` directement :
 * il centralise les clés — donc un renommage ne se cherche pas à travers le code —
 * et il notifie le client HTTP, qui doit poser les deux en-têtes sans dépendre
 * de React.
 */
const TOKEN_KEY = 'tricolis.token'
const ORGANIZATION_KEY = 'tricolis.organizationId'

type Listener = () => void

const listeners = new Set<Listener>()

function notify(): void {
  for (const listener of listeners) listener()
}

export const session = {
  getToken(): string | null {
    return localStorage.getItem(TOKEN_KEY)
  },

  setToken(token: string | null): void {
    if (token === null) localStorage.removeItem(TOKEN_KEY)
    else localStorage.setItem(TOKEN_KEY, token)
    notify()
  },

  getOrganizationId(): string | null {
    return localStorage.getItem(ORGANIZATION_KEY)
  },

  /**
   * Change l'organisation active.
   *
   * L'appelant reste responsable d'invalider les requêtes en cache : ce module
   * ne connaît pas TanStack Query, et l'y coupler rendrait les deux
   * intestables séparément.
   */
  setOrganizationId(id: string | null): void {
    if (id === null) localStorage.removeItem(ORGANIZATION_KEY)
    else localStorage.setItem(ORGANIZATION_KEY, id)
    notify()
  },

  /** Efface tout — déconnexion, ou session refusée par le serveur. */
  clear(): void {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(ORGANIZATION_KEY)
    notify()
  },

  subscribe(listener: Listener): () => void {
    listeners.add(listener)
    return () => listeners.delete(listener)
  },
}
