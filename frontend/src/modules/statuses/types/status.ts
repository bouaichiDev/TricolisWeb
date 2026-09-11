/**
 * Statut du référentiel commun — `StatusResource`.
 *
 * `code` est la valeur réellement stockée dans les colonnes `status` du
 * domaine — `orders.status`, `packages.status` — et `source` dit de quelle
 * entité il s'agit : « draft » n'a pas le même sens pour une commande et pour
 * un colis.
 *
 * `status` est l'identifiant numérique du statut, unique dans sa source.
 */
export interface Status {
  id: string
  source: string
  status: number
  code: string
  label: string
  icon: string | null
  active: boolean
  /** Ce statut déclenche-t-il un envoi au client ? */
  isToSend: boolean
  /** Le contenu de l'entité reste-t-il modifiable dans ce statut ? */
  allowsContentChanges: boolean
  /** Atteindre ce statut exige-t-il un motif ? */
  requiresReason: boolean
  /** Le statut qu'une entité reçoit à sa création. */
  isDefault: boolean
  position: number | null
  createdAt: string
  updatedAt: string
}

/**
 * Statut par défaut d'une entité — `StatusDefaultController::index`.
 *
 * `systemDefault` est ce que la colonne prend faute de choix : c'est ce que
 * l'écran annonce tant que rien n'est coché, et ce que les formulaires
 * préremplissent.
 */
export interface StatusDefault {
  source: string
  statusId: string | null
  code: string | null
  systemDefault: string | null
  options: { id: string; code: string; label: string; active: boolean }[]
}

/**
 * Une transition entre deux statuts — `StatusTransitionResource`.
 *
 * `isManual` distingue ce qu'un opérateur peut poser de ce que seuls les
 * modules produisent : passer une commande en « planifiée » est une transition
 * légitime, mais c'est la planification qui la déclenche, pas un clic.
 */
export interface StatusTransition {
  id: string
  fromStatusId: string
  toStatusId: string
  isManual: boolean
  to?: Status
}

export interface StatusTransitionInput {
  toStatusId: string
  isManual: boolean
}

export interface StatusFilters {
  page: number
  perPage: number
  search?: string
  source?: string
  active?: boolean
  sort?: string
  direction?: 'asc' | 'desc'
}

/** Colonnes acceptées par `StatusController::index` ; toute autre renvoie 422. */
export const STATUS_SORTABLE = ['source', 'status', 'code', 'label', 'position'] as const

export interface StatusPayload {
  source?: string
  status: number
  code: string
  label: string
  icon?: string | null
  active?: boolean
  isToSend?: boolean
  allowsContentChanges?: boolean
  requiresReason?: boolean
  position?: number | null
}

/**
 * Une règle de propagation — `StatusPropagationResource`.
 *
 * « Tous les colis chargés → le service est chargé » : une paire de statuts,
 * chacun portant son entité. Le sens — vers le contenant ou vers le contenu —
 * se lit dans la hiérarchie, jamais dans la règle.
 */
export interface StatusPropagation {
  id: string
  fromStatusId: string
  toStatusId: string
  from: { source: string; code: string; label: string } | null
  to: { source: string; code: string; label: string } | null
  /** `all` : tous les enfants doivent y être. `any` : un seul suffit. */
  mode: string
  active: boolean
}

/** Qui contient qui : `{ order: ['order_service', 'package', 'order_line'], … }`. */
export type StatusHierarchy = Record<string, string[]>

export interface StatusPropagationList {
  rules: StatusPropagation[]
  hierarchy: StatusHierarchy
}

export interface StatusPropagationPayload {
  fromStatusId: string
  toStatusId: string
  mode?: string
  active?: boolean
}
