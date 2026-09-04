import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'

interface FormActionsProps {
  onCancel: () => void
  submitLabel: string
  isSubmitting: boolean
}

/** Pied de formulaire : annuler et valider, avec l'etat d'envoi. */
export function FormActions({ onCancel, submitLabel, isSubmitting }: FormActionsProps) {
  const { t } = useTranslation()

  return (
    <div className="flex justify-end gap-3">
      <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
        {t('common.cancel')}
      </Button>
      <Button type="submit" disabled={isSubmitting}>
        {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
        {submitLabel}
      </Button>
    </div>
  )
}
