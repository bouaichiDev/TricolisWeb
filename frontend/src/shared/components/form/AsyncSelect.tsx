import { Loader2 } from 'lucide-react'
import { useId } from 'react'
import { useTranslation } from 'react-i18next'

import { Label } from '@/shared/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

export interface AsyncOption {
  value: string
  label: string
  hint?: string
}

interface AsyncSelectProps {
  label: string
  value: string
  onChange: (value: string) => void
  options: AsyncOption[]
  isLoading?: boolean
  required?: boolean
  /** Rendu inerte tant qu'une dépendance amont n'est pas choisie. */
  disabled?: boolean
  placeholder?: string
  /** Raison de l'inertie, affichée plutôt que laissée à deviner. */
  description?: string
  error?: string
}

/**
 * Liste déroulante alimentée par une requête.
 *
 * Sert aux sélections dépendantes : une agence n'est proposée que dans
 * l'organisation active, un dépôt que dans l'agence choisie. Quand la
 * dépendance amont manque, le champ est inerte **et dit pourquoi** — un select
 * vide sans explication laisse chercher.
 */
export function AsyncSelect({
  label,
  value,
  onChange,
  options,
  isLoading = false,
  required = false,
  disabled = false,
  placeholder,
  description,
  error,
}: AsyncSelectProps) {
  const { t } = useTranslation()
  const id = useId()
  const inert = disabled || isLoading

  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor={id}>
        {label}
        {required ? <span className="text-destructive"> *</span> : null}
      </Label>

      <Select value={value} onValueChange={onChange} disabled={inert}>
        <SelectTrigger id={id} className="w-full" aria-invalid={error !== undefined}>
          {isLoading ? (
            <span className="flex items-center gap-2 text-muted-foreground">
              <Loader2 className="size-4 animate-spin" aria-hidden />
              {t('common.loading')}
            </span>
          ) : (
            <SelectValue placeholder={placeholder ?? t('common.select')} />
          )}
        </SelectTrigger>

        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              <span className="flex flex-col">
                {option.label}
                {option.hint ? (
                  <span className="text-xs text-muted-foreground">{option.hint}</span>
                ) : null}
              </span>
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {error ? (
        <p className="text-sm text-destructive">{t(error, { defaultValue: error })}</p>
      ) : description ? (
        <p className="text-xs text-muted-foreground">{description}</p>
      ) : null}
    </div>
  )
}
