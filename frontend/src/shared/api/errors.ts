/**
 * Erreurs de l'API, normalisées.
 *
 * Le backend répond toujours de la même façon — `{ message }` pour un refus
 * métier, `{ message, errors }` pour une validation. Cette classe conserve les
 * deux, plus le statut, pour que l'appelant décide sans réinterpréter le corps.
 */
export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>

  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }

  /** Session absente ou expirée : il faut se reconnecter. */
  get isUnauthenticated(): boolean {
    return this.status === 401
  }

  /** Membre de l'organisation, mais sans la permission requise. */
  get isForbidden(): boolean {
    return this.status === 403
  }

  /**
   * Introuvable **dans l'organisation active**.
   *
   * Le backend renvoie 404 aussi bien pour une donnée inexistante que pour une
   * donnée appartenant à une autre organisation : c'est délibéré, et le
   * frontend ne doit surtout pas présenter ce cas comme un problème de droits.
   */
  get isNotFound(): boolean {
    return this.status === 404
  }

  /** L'état du système interdit l'opération — message affichable tel quel. */
  get isConflict(): boolean {
    return this.status === 409
  }

  /** Validation : `errors` porte les champs fautifs. */
  get isValidation(): boolean {
    return this.status === 422
  }
}

/** Erreur réseau : le serveur n'a pas répondu du tout. */
export class NetworkError extends Error {
  constructor(message = 'Le serveur est injoignable.') {
    super(message)
    this.name = 'NetworkError'
  }
}

interface ErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

const DEFAULT_MESSAGES: Record<number, string> = {
  401: 'Votre session a expiré.',
  403: 'Vous n’avez pas la permission requise.',
  404: 'Ressource introuvable.',
  409: 'Cette opération est impossible dans l’état actuel.',
  422: 'Les données fournies sont invalides.',
  500: 'Une erreur interne est survenue.',
}

/**
 * Construit une `ApiError` à partir d'une réponse HTTP en échec.
 *
 * Un corps illisible ne doit pas masquer le statut : c'est lui qui porte
 * l'information exploitable, le message n'est qu'un confort.
 */
export async function toApiError(response: Response): Promise<ApiError> {
  let body: ErrorBody = {}

  try {
    body = (await response.json()) as ErrorBody
  } catch {
    body = {}
  }

  const message =
    body.message ?? DEFAULT_MESSAGES[response.status] ?? 'Une erreur est survenue.'

  return new ApiError(response.status, message, body.errors ?? {})
}
