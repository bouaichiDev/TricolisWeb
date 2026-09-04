import { Controller, type FieldValues, type Path, type UseFormReturn } from 'react-hook-form'

import { Checkbox } from '@/shared/components/ui/checkbox'
import { Label } from '@/shared/components/ui/label'

interface CheckboxFieldProps<T extends FieldValues> {
  form: UseFormReturn<T>
  name: Path<T>
  label: string
  description?: string
  disabled?: boolean
}

/** Case a cocher booleenne reliee a React Hook Form. */
export function CheckboxField<T extends FieldValues>({
  form,
  name,
  label,
  description,
  disabled = false,
}: CheckboxFieldProps<T>) {
  return (
    <Controller
      control={form.control}
      name={name}
      render={({ field }) => (
        <div className="flex items-start gap-3 py-1">
          <Checkbox
            id={name}
            checked={field.value === true}
            onCheckedChange={(checked) => field.onChange(checked === true)}
            disabled={disabled}
          />
          <div className="grid gap-1">
            <Label htmlFor={name} className="font-normal">
              {label}
            </Label>
            {description ? (
              <p className="text-xs text-muted-foreground">{description}</p>
            ) : null}
          </div>
        </div>
      )}
    />
  )
}
