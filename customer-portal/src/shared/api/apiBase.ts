/**
 * L'adresse du backend, en un seul endroit (Phase 8 portail).
 *
 * `VITE_API_URL` est lue au build. En développement et en test, son absence
 * retombe sur l'API locale ; un build de production sans elle est refusé par
 * `vite.config.ts` — il viserait sinon silencieusement `localhost`.
 */
export const API_ORIGIN: string = import.meta.env.VITE_API_URL ?? (import.meta.env.DEV || import.meta.env.MODE === 'test' ? 'http://localhost:8000' : '')

export const API_ROOT = `${API_ORIGIN}/api/v1`
