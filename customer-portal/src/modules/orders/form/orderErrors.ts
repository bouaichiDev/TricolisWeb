import type { FieldPath, UseFormReturn } from 'react-hook-form'

import { ApiError } from '@/shared/api/errors'

import type { OrderFormValues } from './orderSchema'

export type StepKey = 'general' | 'lines' | 'packages' | 'services' | 'documents' | 'review'

/** Les étapes, dans l'ordre. « Documents » n'est montrée qu'à qui peut déposer. */
export const STEPS: { key: StepKey; label: string }[] = [
  { key: 'general', label: 'Informations' },
  { key: 'lines', label: 'Lignes' },
  { key: 'packages', label: 'Colis' },
  { key: 'services', label: 'Services' },
  { key: 'documents', label: 'Documents' },
  { key: 'review', label: 'Récapitulatif' },
]

/** Champs validés à chaque étape avant de passer à la suivante. */
export const STEP_FIELDS: Record<StepKey, FieldPath<OrderFormValues>[]> = {
  general: ['customerReference', 'externalReference', 'workerRemark'],
  lines: ['lines'],
  packages: ['packages'],
  services: ['services'],
  documents: [],
  review: [],
}

const STEP_OF: Record<string, StepKey> = {
  lines: 'lines',
  packages: 'packages',
  services: 'services',
  customerReference: 'general',
  externalReference: 'general',
  workerRemark: 'general',
}

/**
 * Traduit un chemin d'erreur du backend en chemin du formulaire : l'API parle
 * `lineKey`, `address`, `packageKey` ; le formulaire `lineUid`, `recipient`,
 * `packageUids`.
 */
function formPath(key: string): string {
  return key
    .replace(/^lines\.(\d+)\.catalogItemId$/, 'lines.$1.name')
    .replace(/\.lines\.(\d+)\.lineKey$/, '.lines.$1.lineUid')
    .replace(/^services\.(\d+)\.address\./, 'services.$1.recipient.')
    .replace(/^services\.(\d+)\.packages\.\d+\.packageKey$/, 'services.$1.packageUids')
}

/**
 * Pose les erreurs du backend sur les champs, et dit à quelle étape revenir.
 * Un message sans champ connu (`agency`, code métier…) est rendu à part.
 */
export function applyOrderErrors(
  form: UseFormReturn<OrderFormValues>,
  error: unknown,
): { step: StepKey | null; message: string | null } {
  if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
    return { step: null, message: error instanceof Error ? error.message : 'Une erreur inattendue est survenue.' }
  }

  const order = STEPS.map((step) => step.key)
  let step: StepKey | null = null
  let orphan: string | null = null

  for (const [key, messages] of Object.entries(error.errors)) {
    const target = STEP_OF[key.split('.')[0]]

    if (target === undefined) {
      orphan ??= messages[0]
      continue
    }

    form.setError(formPath(key) as FieldPath<OrderFormValues>, { message: messages[0] })

    // L'étape à rouvrir est la première qui porte une erreur.
    if (step === null || order.indexOf(target) < order.indexOf(step)) step = target
  }

  return { step, message: orphan ?? (step === null ? error.message : 'Corrigez les champs signalés.') }
}
