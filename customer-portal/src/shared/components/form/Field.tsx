import { useId, type InputHTMLAttributes } from 'react'
import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'

import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import { fieldError } from './fieldError'

interface FieldProps<T extends FieldValues> extends Omit<InputHTMLAttributes<HTMLInputElement>, 'name' | 'form'> {
  form: UseFormReturn<T>
  name: Path<T>
  label: string
}

/** Champ texte lié à react-hook-form, avec son message d'erreur. */
export function Field<T extends FieldValues>({ form, name, label, ...input }: FieldProps<T>) {
  const id = useId()
  const message = fieldError(form, name)

  return (
    <div className="grid gap-1.5">
      <Label htmlFor={id}>{label}</Label>
      <Input
        id={id}
        aria-invalid={message !== undefined}
        aria-describedby={message ? `${id}-error` : undefined}
        {...input}
        {...form.register(name)}
      />
      {message && (
        <p id={`${id}-error`} className="text-destructive text-sm">
          {message}
        </p>
      )}
    </div>
  )
}

