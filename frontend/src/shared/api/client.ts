import { ApiError, NetworkError, toApiError } from './errors'
import { session } from './session'

/**
 * Client HTTP unique de l'application.
 *
 * Il porte les quatre choses que le backend attend de chaque requête métier :
 * le préfixe `/api/v1`, le jeton Bearer, l'en-tête `X-Organization-Id`, et
 * `Accept: application/json` — sans lequel Laravel renvoie du HTML sur une
 * erreur de validation.
 *
 * Aucun composant n'appelle `fetch` directement : c'est ce qui garantit qu'on
 * ne peut pas oublier l'en-tête d'organisation sur une route, et donc lire les
 * données de la mauvaise organisation.
 */
const BASE_URL = `${import.meta.env.VITE_API_URL ?? 'http://localhost:8000'}/api/v1`

type Query = Record<string, string | number | boolean | null | undefined>

interface RequestOptions {
  /** Paramètres d'URL ; les valeurs nulles ou vides sont omises. */
  query?: Query
  /** Corps JSON. Ignoré pour `GET`. */
  body?: unknown
  /** Corps multipart, pour les téléversements. Exclusif avec `body`. */
  formData?: FormData
  signal?: AbortSignal
}

/**
 * Un booléen tel que la règle `boolean` de Laravel l'accepte.
 *
 * Elle admet `1`, `0`, `"1"` et `"0"` — **pas** `"true"` ni `"false"`, qui sont
 * pourtant ce que `String(true)` produit. Un filtre `active=true` repartait
 * donc en 422, et l'écran affichait une liste vide sans dire pourquoi.
 *
 * La conversion est faite ici, une fois : la laisser à chaque appelant
 * garantissait qu'un seul l'oublie.
 */
function queryValue(value: string | number | boolean): string {
  if (typeof value === 'boolean') return value ? '1' : '0'

  return String(value)
}

function buildUrl(path: string, query?: Query): string {
  const url = new URL(`${BASE_URL}${path}`)

  for (const [key, value] of Object.entries(query ?? {})) {
    // `false` est un filtre en soi — « les inactifs » — et doit passer ; seuls
    // l'absence et la chaîne vide sont omises.
    if (value === null || value === undefined || value === '') continue
    url.searchParams.set(key, queryValue(value))
  }

  return url.toString()
}

function buildHeaders(hasJsonBody: boolean): Headers {
  const headers = new Headers({ Accept: 'application/json' })

  if (hasJsonBody) headers.set('Content-Type', 'application/json')

  const token = session.getToken()
  if (token) headers.set('Authorization', `Bearer ${token}`)

  const organizationId = session.getOrganizationId()
  if (organizationId) headers.set('X-Organization-Id', organizationId)

  return headers
}

async function request<T>(
  method: string,
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const { query, body, formData, signal } = options
  const hasJsonBody = body !== undefined && formData === undefined

  let response: Response

  try {
    response = await fetch(buildUrl(path, query), {
      method,
      headers: buildHeaders(hasJsonBody),
      body: formData ?? (hasJsonBody ? JSON.stringify(body) : undefined),
      signal,
    })
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    throw new NetworkError()
  }

  if (!response.ok) {
    const apiError = await toApiError(response)

    // Une session refusée est effacée ici, à la source : sans cela chaque
    // requête suivante repartirait avec le même jeton mort.
    if (apiError.isUnauthenticated) session.clear()

    throw apiError
  }

  if (response.status === 204) return undefined as T

  return (await response.json()) as T
}

/**
 * Récupère un fichier, en conservant l'authentification.
 *
 * `GET /documents/{id}/download` est une route authentifiée : un `<a href>`
 * partirait sans l'en-tête `Bearer` ni `X-Organization-Id` et reviendrait en
 * 401. Le corps n'est pas du JSON, d'où cette variante — la gestion d'erreur
 * reste celle de `request`.
 */
async function requestBlob(path: string): Promise<Blob> {
  let response: Response

  try {
    response = await fetch(buildUrl(path), { method: 'GET', headers: buildHeaders(false) })
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    throw new NetworkError()
  }

  if (!response.ok) {
    const apiError = await toApiError(response)
    if (apiError.isUnauthenticated) session.clear()

    throw apiError
  }

  return response.blob()
}

export const api = {
  get: <T>(path: string, options?: Omit<RequestOptions, 'body' | 'formData'>) =>
    request<T>('GET', path, options),
  post: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>('POST', path, { ...options, body }),
  patch: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>('PATCH', path, { ...options, body }),
  // `PUT` sert aux remplacements complets — le jeu de transitions d'un statut,
  // par exemple. `PATCH` dirait une modification partielle, ce qui serait faux.
  put: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>('PUT', path, { ...options, body }),
  delete: <T>(path: string, options?: RequestOptions) =>
    request<T>('DELETE', path, options),
  upload: <T>(path: string, formData: FormData, options?: RequestOptions) =>
    request<T>('POST', path, { ...options, formData }),
  /** Téléchargement authentifié : le corps est un fichier, pas du JSON. */
  blob: (path: string) => requestBlob(path),
}

export { ApiError, NetworkError }
