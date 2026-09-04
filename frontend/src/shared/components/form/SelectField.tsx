import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Controller } from 'react-hook-form'

import { Label } from '@/shared/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

export interface SelectOption {
  value: string
  label: string
}

interface SelectFieldProps<T extends FieldValues> {
  form: UseFormReturn<T>
  name: Path<T>
  label: string
  options: SelectOption[]
  required?: boolean
  disabled?: boolean
  placeholder?: string
  description?: string
}

/** Liste deroulante reliee a React Hook Form, meme contrat i18n que `TextField`. */
export function SelectField<T extends FieldValues>({
  form,
  name,
  label,
  options,
  required = false,
  disabled = false,
  placeholder,
  description,
}: SelectFieldProps<T>) {
  const { t } = useTranslation()
  const error = form.formState.errors[name]
  const message = typeof error?.message === 'string' ? error.message : undefined

  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor={name}>
        {label}
        {required ? <span className="text-destructive"> *</span> : null}
      </Label>

      <Controller
        control={form.control}
        name={name}
        render={({ field }) => (
          <Select
            value={field.value ?? ''}
            onValueChange={field.onChange}
            disabled={disabled || options.length === 0}
          >
            <SelectTrigger id={name} aria-invalid={message !== undefined} className="w-full">
              <SelectValue placeholder={placeholder ?? t('common.select')} />
            </SelectTrigger>
            <SelectContent>
              {options.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      />

      {message ? (
        <p className="text-sm text-destructive">{t(message, { defaultValue: message })}</p>
      ) : description ? (
        <p className="text-xs text-muted-foreground">{description}</p>
      ) : null}
    </div>
  )
}
