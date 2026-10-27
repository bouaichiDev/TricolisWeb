import { API_ROOT } from './apiBase'
import { ApiError, NetworkError, toApiError } from './errors'
import { session } from './session'

/**
 * Client HTTP unique du portail.
 *
 * Il ne parle qu'à `/api/v1/customer` et n'envoie **aucun** identifiant de
 * client : le backend lit le client courant dans le jeton. Aucun composant
 * n'appelle `fetch` directement.
 */
const BASE_URL = `${API_ROOT}/customer`

type Query = Record<string, string | number | boolean | string[] | null | undefined>

interface RequestOptions {
  query?: Query
  body?: unknown
  /** Corps multipart, pour un dépôt de fichier. Exclusif avec `body`. */
  formData?: FormData
  /** Le corps est un fichier, pas du JSON. */
  asBlob?: boolean
  signal?: AbortSignal
}

function buildUrl(path: string, query?: Query): string {
  const url = new URL(`${BASE_URL}${path}`)

  for (const [key, value] of Object.entries(query ?? {})) {
    if (value === null || value === undefined || value === '') continue

    // `cle[]=a&cle[]=b` : la seule forme que Laravel relit comme un tableau.
    if (Array.isArray(value)) {
      value.forEach((entry) => url.searchParams.append(`${key}[]`, entry))
      continue
    }

    url.searchParams.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }

  return url.toString()
}

function buildHeaders(hasBody: boolean): Headers {
  const headers = new Headers({ Accept: 'application/json' })

  if (hasBody) headers.set('Content-Type', 'application/json')

  const token = session.getToken()
  if (token) headers.set('Authorization', `Bearer ${token}`)

  return headers
}

async function request<T>(method: string, path: string, options: RequestOptions = {}): Promise<T> {
  const { query, body, formData, signal } = options
  const hasBody = body !== undefined && formData === undefined
  let response: Response

  try {
    response = await fetch(buildUrl(path, query), {
      method,
      headers: buildHeaders(hasBody),
      body: formData ?? (hasBody ? JSON.stringify(body) : undefined),
      signal,
    })
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    throw new NetworkError()
  }

  if (!response.ok) {
    const apiError = await toApiError(response)

    // Session morte ou accès coupé : on l'abandonne à la source, sinon chaque
    // requête suivante repartirait avec le même jeton.
    if (apiError.isUnauthenticated || apiError.isAccessRevoked) session.clear()

    throw apiError
  }

  if (response.status === 204) return undefined as T

  if (options.asBlob) return (await response.blob()) as T

  return (await response.json()) as T
}

export const api = {
  get: <T>(path: string, options?: Omit<RequestOptions, 'body'>) => request<T>('GET', path, options),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, { body }),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, { body }),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, { body }),
  delete: <T>(path: string) => request<T>('DELETE', path),
  upload: <T>(path: string, formData: FormData) => request<T>('POST', path, { formData }),
  /** Téléchargement authentifié : un `<a href>` partirait sans le jeton. */
  blob: (path: string) => request<Blob>('GET', path, { asBlob: true }),
}

export { ApiError, NetworkError }
