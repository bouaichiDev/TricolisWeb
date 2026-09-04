import { setupServer } from 'msw/node'

/**
 * Serveur d'interception HTTP.
 *
 * Il démarre sans aucun gestionnaire : chaque test déclare les siens avec
 * `server.use(...)`. `onUnhandledRequest: 'error'` est délibéré — un appel non
 * prévu doit faire échouer le test, sinon un composant qui interroge une route
 * inattendue passerait inaperçu.
 */
export const server = setupServer()

export const API = 'http://localhost:8000/api/v1'
