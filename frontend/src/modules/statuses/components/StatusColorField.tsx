import { X } from 'lucide-react'
import { useId } from 'react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import { Label } from '@/shared/components/ui/label'

interface StatusColorFieldProps {
  /** `#rrggbb`, ou chaîne vide : aucune couleur. */
  value: string
  onChange: (value: string) => void
  error?: string
}

/**
 * La couleur d'un statut, telle que le portail client l'affiche.
 *
 * Facultative : vide, le portail garde sa teinte par défaut. Un bouton la
 * retire — un sélecteur de couleur natif ne sait pas revenir à « aucune ».
 */
export function StatusColorField({ value, onChange, error }: StatusColorFieldProps) {
  const { t } = useTranslation()
  const id = useId()

  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={id}>{t('statuses.fields.color')}</Label>
      <div className="flex items-center gap-2">
        <input
          id={id}
          type="color"
          value={value || '#64748b'}
          onChange={(event) => onChange(event.target.value)}
          className="h-9 w-14 cursor-pointer rounded-md border bg-background p-1"
          aria-invalid={error !== undefined}
        />
        <span className="font-mono text-sm text-muted-foreground">{value || t('statuses.colorNone')}</span>
        {value !== '' ? (
          <Button type="button" variant="ghost" size="sm" onClick={() => onChange('')} aria-label={t('statuses.colorClear')}>
            <X className="size-4" aria-hidden />
          </Button>
        ) : null}
      </div>
      <p className="text-xs text-muted-foreground">{t('statuses.colorHint')}</p>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
    </div>
  )
}
