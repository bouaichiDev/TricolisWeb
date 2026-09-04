import type { FieldValues, Path, PathValue, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { Label } from '@/shared/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

import { useStatusOptions } from '../hooks/useStatuses'

interface ReferentialStatusSelectProps<T extends FieldValues> {
  form: UseFormReturn<T>
  name: Path<T>
  label: string
  /** Entité concernée : `provider`, `driver`, `vehicle`… */
  source: string
}

/**
 * Sélecteur de statut alimenté par le référentiel.
 *
 * À la différence de `StatusSelect`, qui reçoit ses valeurs de l'appelant, ce
 * composant les demande à `statuses`. Une liste codée dans le module
 * divergerait dès qu'un administrateur ajoute un statut — et c'est justement ce
 * que le référentiel permet.
 *
 * La valeur envoyée est le **code**, jamais l'identifiant : c'est le code que
 * la colonne `status` de l'entité stocke.
 */
export function ReferentialStatusSelect<T extends FieldValues>({
  form,
  name,
  label,
  source,
}: ReferentialStatusSelectProps<T>) {
  const { t } = useTranslation()

  const current = form.watch(name) as string | undefined
  const { isLoading, options } = useStatusOptions(source, current)

  const error = form.formState.errors[name]
  const id = `status-${String(name)}`

  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor={id}>{label}</Label>

      <Select
        value={current ?? ''}
        onValueChange={(value) =>
          form.setValue(name, value as PathValue<T, Path<T>>, {
            shouldDirty: true,
            shouldValidate: true,
          })
        }
        disabled={isLoading}
      >
        <SelectTrigger id={id} aria-invalid={error !== undefined}>
          <SelectValue placeholder={isLoading ? t('common.loading') : t('statuses.pick')} />
        </SelectTrigger>

        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {error === undefined ? null : (
        <p className="text-xs text-destructive">
          {t(String(error.message ?? 'errors.validation'))}
        </p>
      )}
    </div>
  )
}
