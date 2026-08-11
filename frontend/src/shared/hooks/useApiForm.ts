import { useState } from 'react'
import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/client'

/**
 * Traduit une erreur d'API en erreurs de formulaire.
 *
 * Le §31 l'exige : un 422 doit se poser sur les champs fautifs, pas dans un
 * bandeau générique. Le backend renvoie ses clés en camelCase — exactement les
 * noms de champs du formulaire — ce qui permet de les rebrancher directement.
 *
 * Les autres statuts n'ont pas de champ à désigner : ils remontent dans un
 * message global. Un 409 en particulier porte une phrase rédigée pour être
 * affichée telle quelle.
 */
export function useApiFormError<T extends FieldValues>(form: UseFormReturn<T>) {
  const { t } = useTranslation()
  const [formError, setFormError] = useState<string | null>(null)

  const handleError = (error: unknown): void => {
    if (!(error instanceof ApiError)) {
      setFormError(error instanceof Error ? error.message : t('errors.unexpected'))
      return
    }

    if (error.isValidation) {
      const entries = Object.entries(error.errors)

      for (const [field, messages] of entries) {
        form.setError(field as Path<T>, { type: 'server', message: messages[0] })
      }

      // Un 422 dont aucune clé ne correspond à un champ affiché laisserait
      // l'utilisateur sans explication : le message général prend le relais.
      const known = entries.some(([field]) => field in form.getValues())
      setFormError(known ? null : error.message)
      return
    }

    setFormError(error.message)
  }

  return { formError, setFormError, handleError, clearError: () => setFormError(null) }
}
