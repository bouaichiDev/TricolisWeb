import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'

/**
 * L'erreur d'une décision, lisible : le 409 dit que la demande a été tranchée
 * ailleurs ou que le stock refuse la sortie ; le 422 porte le champ fautif ;
 * le 403 et le 404 gardent le message du serveur.
 */
export function DecisionError({ error }: { error: Error | null }) {
  const { t } = useTranslation()

  if (error === null) return null

  const message =
    error instanceof ApiError
      ? (Object.values(error.errors)[0]?.[0] ?? error.message)
      : t('errors.unexpected')

  return (
    <Alert variant="destructive" role="alert">
      <AlertDescription>
        {error instanceof ApiError && error.isConflict ? `${t('stock.adjustments.conflict')} ` : ''}
        {message}
      </AlertDescription>
    </Alert>
  )
}
