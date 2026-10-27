import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'

import { ApiError } from '@/shared/api/errors'

/**
 * Reporte les erreurs de validation du backend sur les champs du formulaire.
 *
 * Renvoie les messages qui ne correspondent à aucun champ connu (`token`,
 * `member`…) : l'appelant les affiche en tête du formulaire.
 */
export function applyServerErrors<T extends FieldValues>(
  form: UseFormReturn<T>,
  error: unknown,
  fields: readonly Path<T>[],
): string[] {
  if (!(error instanceof ApiError) || !error.isValidation) return []

  const orphans: string[] = []

  for (const [field, messages] of Object.entries(error.errors)) {
    if ((fields as readonly string[]).includes(field)) {
      form.setError(field as Path<T>, { message: messages[0] })
    } else {
      orphans.push(messages[0])
    }
  }

  return orphans
}
