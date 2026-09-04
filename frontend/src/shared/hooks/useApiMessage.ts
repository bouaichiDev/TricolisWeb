import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'

/**
 * Le message qu'un écran doit montrer quand une action échoue.
 *
 * `useApiFormError` couvre les formulaires : il repose les 422 sur les champs
 * fautifs. Les écrans de cette phase ne sont pas des formulaires — on y compose
 * une facture en cochant des prestations — et un 422 y désigne souvent un champ
 * qui n'existe pas à l'écran (`lines.0.orderServiceId`). Sans relais, l'action
 * échouerait en silence : le bouton se réactive, et rien n'explique pourquoi.
 *
 * Les messages de validation sont donc rendus tels quels, en plus du message
 * général — c'est le serveur qui sait ce qui bloque.
 */
export function useApiMessage(error: unknown): string | null {
  const { t } = useTranslation()

  if (error === null || error === undefined) return null

  if (!(error instanceof ApiError)) {
    return error instanceof Error ? error.message : t('errors.unexpected')
  }

  const details = Object.values(error.errors)
    .map((messages) => messages[0])
    .filter(Boolean)

  return details.length > 0 ? `${error.message} ${details.join(' ')}` : error.message
}
