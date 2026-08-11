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

function buildUrl(path: string, query?: Query): string {
  const url = new URL(`${BASE_URL}${path}`)

  for (const [key, value] of Object.entries(query ?? {})) {
    if (value === null || value === undefined || value === '') continue
    url.searchParams.set(key, String(value))
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

export const api = {
  get: <T>(path: string, options?: Omit<RequestOptions, 'body' | 'formData'>) =>
    request<T>('GET', path, options),
  post: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>('POST', path, { ...options, body }),
  patch: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>('PATCH', path, { ...options, body }),
  delete: <T>(path: string, options?: RequestOptions) =>
    request<T>('DELETE', path, options),
  upload: <T>(path: string, formData: FormData, options?: RequestOptions) =>
    request<T>('POST', path, { ...options, formData }),
}

export { ApiError, NetworkError }
