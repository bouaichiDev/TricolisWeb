import { ApiError } from '@/shared/api/errors'

/** Valeur d'affichage d'un champ texte : `null` et `undefined` deviennent vide. */
export const text = (value: string | null | undefined): string => value ?? ''

/** Valeur d'affichage d'un nombre, que l'API renvoie parfois en chaîne. */
export const num = (value: number | string | null | undefined): string =>
  value === null || value === undefined ? '' : String(value)

/** Chaîne vide envoyée comme `null` : c'est ainsi que le serveur efface un champ. */
export const blank = (value: string): string | null =>
  value.trim() === '' ? null : value.trim()

/**
 * Nombre omis plutôt qu'envoyé à zéro.
 *
 * Les règles `sometimes` du backend distinguent « non fourni » de « zéro » ;
 * envoyer `0` pour un champ laissé vide inventerait une donnée.
 */
export const optional = (value: string): number | undefined =>
  value.trim() === '' ? undefined : Number(value)

/** Première erreur de chaque champ d'un 422, prête à être posée sous les champs. */
export const fieldErrorsOf = (error: ApiError): Record<string, string> =>
  Object.fromEntries(
    Object.entries(error.errors).map(([field, messages]) => [field, messages[0]]),
  )
