import { ApiError } from '@/shared/api/errors'

import type { SerializedOrder } from './serializeOrder'

export type OrderStep = 'general' | 'lines' | 'packages' | 'services' | 'review'

export interface OrderIssueSub {
  kind: 'lines' | 'contacts' | 'packages'
  index: number
}

export interface OrderIssue {
  /** Chemin renvoyé par le serveur, conservé tel quel pour le diagnostic. */
  path: string
  message: string
  step: OrderStep
  /** Clé stable de l'élément du brouillon visé, quand le chemin en désigne un. */
  entityKey: string | null
  /** Collection imbriquée visée à l'intérieur de cet élément. */
  sub: OrderIssueSub | null
  /** Dernier segment nommé du chemin : le champ fautif. */
  field: string
}

export interface OrderErrorReport {
  issues: OrderIssue[]
  /** Étapes portant au moins une erreur, dans l'ordre du parcours. */
  stepsInError: OrderStep[]
  /** Message affiché en bandeau : refus métier, ou 422 sans chemin exploitable. */
  message: string | null
}

const STEP_ORDER: OrderStep[] = ['general', 'lines', 'packages', 'services', 'review']

const EMPTY: OrderErrorReport = { issues: [], stepsInError: [], message: null }

/**
 * Traduit un 422 imbriqué en erreurs rattachées aux éléments du formulaire.
 *
 * Le §34 l'exige : `services.0.contacts.0.email` doit désigner le contact
 * saisi, pas un champ anonyme, et la saisie doit survivre à l'échec. Les
 * positions du serveur portent sur le tableau **envoyé** — les colis ayant été
 * réordonnés à la sérialisation — d'où les tableaux de clés de `SerializedOrder`.
 *
 * Un chemin non reconnu n'est pas jeté : il remonte avec `entityKey` à `null`,
 * et son étape reste `general` pour rester visible.
 */
export function mapOrderErrors(error: unknown, keys: SerializedOrder): OrderErrorReport {
  if (!(error instanceof ApiError)) {
    return { ...EMPTY, message: error instanceof Error ? error.message : null }
  }

  if (!error.isValidation) {
    return { ...EMPTY, message: error.message }
  }

  const issues: OrderIssue[] = []

  for (const [path, messages] of Object.entries(error.errors)) {
    issues.push(parseIssue(path, messages[0] ?? error.message, keys))
  }

  const steps = new Set(issues.map((issue) => issue.step))

  return {
    issues,
    stepsInError: STEP_ORDER.filter((step) => steps.has(step)),
    // Le bandeau ne répète pas un message déjà posé sur un champ ; il ne sert
    // que quand aucune erreur n'a trouvé sa place.
    message: issues.length === 0 ? error.message : null,
  }
}

function parseIssue(path: string, message: string, keys: SerializedOrder): OrderIssue {
  const segments = path.split('.')
  const root = segments[0]
  const index = Number(segments[1])

  const base = { path, message, entityKey: null as string | null, sub: null as OrderIssueSub | null }

  if (root === 'lines' && Number.isInteger(index)) {
    return {
      ...base,
      step: 'lines',
      entityKey: keys.lineKeys[index] ?? null,
      field: segments.slice(2).join('.') || root,
    }
  }

  if (root === 'packages' && Number.isInteger(index)) {
    return {
      ...base,
      step: 'packages',
      entityKey: keys.packageKeys[index] ?? null,
      sub: subOf(segments, 2, ['lines']),
      field: fieldOf(segments, 2),
    }
  }

  if (root === 'services' && Number.isInteger(index)) {
    return {
      ...base,
      step: 'services',
      entityKey: keys.serviceKeys[index] ?? null,
      sub: subOf(segments, 2, ['contacts', 'packages']),
      field: fieldOf(segments, 2),
    }
  }

  return { ...base, step: 'general', field: path }
}

/** Collection imbriquée visée, si le chemin en désigne une. */
function subOf(segments: string[], at: number, allowed: string[]): OrderIssueSub | null {
  const kind = segments[at]
  const index = Number(segments[at + 1])

  if (!allowed.includes(kind) || !Number.isInteger(index)) return null

  return { kind: kind as OrderIssueSub['kind'], index }
}

/** Champ fautif : le reste du chemin une fois l'élément et son sous-élément retirés. */
function fieldOf(segments: string[], at: number): string {
  const rest = segments.slice(at)

  if (rest.length >= 2 && Number.isInteger(Number(rest[1]))) {
    return rest.slice(2).join('.') || rest[0]
  }

  return rest.join('.') || segments[0]
}

/** Erreurs d'un élément précis, avec ou sans sous-élément. */
export function issuesOf(
  report: OrderErrorReport,
  entityKey: string,
  sub?: OrderIssueSub,
): OrderIssue[] {
  return report.issues.filter((issue) => {
    if (issue.entityKey !== entityKey) return false
    if (sub === undefined) return issue.sub === null
    return issue.sub?.kind === sub.kind && issue.sub.index === sub.index
  })
}

/** Message posé sur un champ donné, ou `undefined` s'il n'y en a pas. */
export function fieldError(issues: OrderIssue[], field: string): string | undefined {
  return issues.find((issue) => issue.field === field)?.message
}

/** Erreurs d'en-tête, celles qui ne visent aucune collection. */
export function generalIssues(report: OrderErrorReport): OrderIssue[] {
  return report.issues.filter((issue) => issue.step === 'general')
}
