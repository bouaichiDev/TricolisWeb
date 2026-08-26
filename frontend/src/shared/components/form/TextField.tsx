import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

interface TextFieldProps<T extends FieldValues> {
  form: UseFormReturn<T>
  name: Path<T>
  label: string
  type?: 'text' | 'email' | 'password' | 'tel' | 'number' | 'date' | 'time' | 'datetime-local'
  required?: boolean
  disabled?: boolean
  placeholder?: string
  description?: string
}

/**
 * Champ texte relié à React Hook Form.
 *
 * Il traduit les messages d'erreur : les schémas Zod portent des **clés i18n**
 * plutôt que des phrases, ce qui les rend indépendants de la langue. Un message
 * venant du serveur, lui, arrive déjà rédigé — la traduction le laisse alors
 * passer tel quel grâce à `defaultValue`.
 */
export function TextField<T extends FieldValues>({
  form,
  name,
  label,
  type = 'text',
  required = false,
  disabled = false,
  placeholder,
  description,
}: TextFieldProps<T>) {
  const { t } = useTranslation()
  const error = form.formState.errors[name]
  const message = typeof error?.message === 'string' ? error.message : undefined

  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor={name}>
        {label}
        {required ? <span className="text-destructive"> *</span> : null}
      </Label>

      <Input
        id={name}
        type={type}
        disabled={disabled}
        placeholder={placeholder}
        aria-invalid={message !== undefined}
        aria-describedby={message ? `${name}-error` : undefined}
        {...form.register(name)}
      />

      {message ? (
        <p id={`${name}-error`} className="text-sm text-destructive">
          {t(message, { defaultValue: message })}
        </p>
      ) : description ? (
        <p className="text-xs text-muted-foreground">{description}</p>
      ) : null}
    </div>
  )
}
