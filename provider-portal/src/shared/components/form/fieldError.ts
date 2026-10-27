import { get, type FieldValues, type Path, type UseFormReturn } from 'react-hook-form'

/**
 * Message d'erreur d'un champ, y compris imbriqué (`services.0.addressId`) :
 * `formState.errors[name]` ne lit que le premier niveau.
 */
export function fieldError<T extends FieldValues>(form: UseFormReturn<T>, name: Path<T>): string | undefined {
  const error = get(form.formState.errors, name) as { message?: unknown } | undefined

  return typeof error?.message === 'string' ? error.message : undefined
}
