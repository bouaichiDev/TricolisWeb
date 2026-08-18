import { useId } from 'react'

import { Checkbox } from '@/shared/components/ui/checkbox'
import { Label } from '@/shared/components/ui/label'

interface ControlledCheckboxProps {
  label: string
  checked: boolean
  onChange: (checked: boolean) => void
  description?: string
  disabled?: boolean
}

/** Case à cocher pilotée par un état extérieur — pendant de `ControlledField`. */
export function ControlledCheckbox({
  label,
  checked,
  onChange,
  description,
  disabled = false,
}: ControlledCheckboxProps) {
  const id = useId()

  return (
    <div className="flex items-start gap-3 py-1">
      <Checkbox
        id={id}
        checked={checked}
        onCheckedChange={(value) => onChange(value === true)}
        disabled={disabled}
      />
      <div className="grid gap-1">
        <Label htmlFor={id} className="font-normal">
          {label}
        </Label>
        {description ? <p className="text-xs text-muted-foreground">{description}</p> : null}
      </div>
    </div>
  )
}
