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

interface StatusSelectProps<T extends FieldValues> {
  form: UseFormReturn<T>
  name: Path<T>
  label: string
  /** Valeurs acceptees par l'API pour ce champ. Aucune n'est inventee ici. */
  options: readonly string[]
  /**
   * Espace de noms i18n des libelles.
   *
   * Le composant sert aussi a des listes qui ne sont pas des statuts — type
   * d'adresse, role de contact. Le prefixe evite de les entasser sous `status`,
   * ou « livraison » cohabiterait avec « actif ».
   */
  translationPrefix?: string
}

/**
 * Selecteur de statut.
 *
 * Les valeurs sont passees par l'appelant plutot que devinees : le backend
 * laisse plusieurs champs `status` en chaine libre, et une liste codee ici
 * divergerait au premier statut ajoute.
 */
export function StatusSelect<T extends FieldValues>({
  form,
  name,
  label,
  options,
  translationPrefix = 'status',
}: StatusSelectProps<T>) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor={name}>{label}</Label>
      <Select
        value={form.watch(name)}
        onValueChange={(value) =>
          form.setValue(name, value as PathValue<T, Path<T>>, { shouldDirty: true })
        }
      >
        <SelectTrigger id={name}>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option} value={option}>
              {t(`${translationPrefix}.${option}`, { defaultValue: option })}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )
}
