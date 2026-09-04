import { useId } from 'react'
import { useTranslation } from 'react-i18next'

import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

interface ControlledFieldProps {
  label: string
  value: string
  onChange: (value: string) => void
  /**
   * `password` masque la saisie : un mot de passe pose par un administrateur se
   * lit par-dessus l'epaule comme un autre.
   */
  type?: 'text' | 'number' | 'date' | 'time' | 'datetime-local' | 'email' | 'tel' | 'password'
  multiline?: boolean
  required?: boolean
  disabled?: boolean
  placeholder?: string
  description?: string
  error?: string
  /** Pas négatif pour les quantités décimales ; laissé libre par défaut. */
  step?: string
  min?: string
}

/**
 * Champ texte piloté par un état extérieur.
 *
 * Le formulaire de commande tient un brouillon imbriqué — lignes, colis,
 * services, contacts — que React Hook Form ne peut pas décrire sans transformer
 * chaque tableau en chemin indexé, exactement ce que le §23 interdit d'utiliser
 * comme identité. L'état est donc porté par le module, et ce champ s'y branche.
 *
 * Le contrat i18n reste celui de `TextField` : un message venant du serveur
 * traverse `t` sans être traduit grâce à `defaultValue`.
 */
export function ControlledField({
  label,
  value,
  onChange,
  type = 'text',
  multiline = false,
  required = false,
  disabled = false,
  placeholder,
  description,
  error,
  step,
  min,
}: ControlledFieldProps) {
  const { t } = useTranslation()
  const id = useId()

  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor={id}>
        {label}
        {required ? <span className="text-destructive"> *</span> : null}
      </Label>

      {multiline ? (
        <Textarea
          id={id}
          value={value}
          disabled={disabled}
          placeholder={placeholder}
          aria-invalid={error !== undefined}
          onChange={(event) => onChange(event.target.value)}
        />
      ) : (
        <Input
          id={id}
          type={type}
          value={value}
          step={step}
          min={min}
          disabled={disabled}
          placeholder={placeholder}
          aria-invalid={error !== undefined}
          onChange={(event) => onChange(event.target.value)}
        />
      )}

      {error ? (
        <p className="text-sm text-destructive">{t(error, { defaultValue: error })}</p>
      ) : description ? (
        <p className="text-xs text-muted-foreground">{description}</p>
      ) : null}
    </div>
  )
}
