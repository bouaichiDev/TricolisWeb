import { useId } from 'react'
import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'

import { Label } from '@/shared/components/ui/label'

import { fieldError } from './fieldError'

interface SelectFieldProps<T extends FieldValues> {
  form: UseFormReturn<T>
  name: Path<T>
  label: string
  options: { value: string; label: string }[]
  placeholder?: string
}

/** Liste déroulante native liée à react-hook-form. */
export function SelectField<T extends FieldValues>({ form, name, label, options, placeholder }: SelectFieldProps<T>) {
  const id = useId()
  const message = fieldError(form, name)

  return (
    <div className="grid gap-1.5">
      <Label htmlFor={id}>{label}</Label>
      <select
        id={id}
        aria-invalid={message !== undefined}
        className="border-input aria-invalid:border-destructive h-9 rounded-md border bg-transparent px-3 text-sm"
        {...form.register(name)}
      >
        {placeholder !== undefined && <option value="">{placeholder}</option>}
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      {message && <p className="text-destructive text-sm">{message}</p>}
    </div>
  )
}
