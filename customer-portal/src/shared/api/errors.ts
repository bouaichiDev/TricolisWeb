/**
 * Erreurs de l'API du portail, normalisées.
 *
 * Le backend répond `{ message }` pour un refus, `{ message, errors }` pour
 * une validation, et `{ message, code }` quand le portail doit réagir
 * autrement qu'en affichant le message (accès révoqué, choix du client).
 */
export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>
  readonly code: string | null
  readonly body: Record<string, unknown>

  constructor(
    status: number,
    message: string,
    errors: Record<string, string[]> = {},
    code: string | null = null,
    body: Record<string, unknown> = {},
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
    this.code = code
    this.body = body
  }

  get isUnauthenticated(): boolean {
    return this.status === 401
  }

  /**
   * L'accès au portail a été coupé : membre ou client désactivé, ou jeton
   * qui n'est pas un jeton de portail. La session doit être abandonnée.
   */
  get isAccessRevoked(): boolean {
    return this.status === 403 && this.code === 'customer_access_revoked'
  }

  get isForbidden(): boolean {
    return this.status === 403
  }

  /**
   * Introuvable **dans le client courant** : le backend répond de même pour
   * une donnée d'un autre client. Ce n'est pas un problème de droits.
   */
  get isNotFound(): boolean {
    return this.status === 404
  }

  get isValidation(): boolean {
    return this.status === 422
  }

  /** Premier message d'un champ, ou `undefined`. */
  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

export class NetworkError extends Error {
  constructor(message = 'Le serveur est injoignable.') {
    super(message)
    this.name = 'NetworkError'
  }
}

const DEFAULT_MESSAGES: Record<number, string> = {
  401: 'Votre session a expiré.',
  403: 'Vous n’avez pas la permission requise.',
  404: 'Ressource introuvable.',
  409: 'Cette opération est impossible dans l’état actuel.',
  422: 'Les données fournies sont invalides.',
  429: 'Trop de tentatives. Réessayez dans une minute.',
  500: 'Une erreur interne est survenue.',
}

export async function toApiError(response: Response): Promise<ApiError> {
  let body: Record<string, unknown> = {}

  try {
    body = (await response.json()) as Record<string, unknown>
  } catch {
    // Corps illisible : le statut suffit.
  }

  const message =
    typeof body.message === 'string' && body.message !== ''
      ? body.message
      : (DEFAULT_MESSAGES[response.status] ?? 'Une erreur est survenue.')

  return new ApiError(
    response.status,
    message,
    (body.errors as Record<string, string[]> | undefined) ?? {},
    typeof body.code === 'string' ? body.code : null,
    body,
  )
}

/** Message affichable pour n'importe quelle erreur. */
export function errorMessage(error: unknown): string {
  if (error instanceof ApiError || error instanceof NetworkError) return error.message

  return 'Une erreur inattendue est survenue.'
}
