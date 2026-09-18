import { useState } from 'react'

import { ApiError } from '@/shared/api/errors'

import type { ServiceContactsError } from '../../hooks/useSaveOrderService'
import type { OrderErrorReport } from '../../schemas/orderErrors'
import {
  contactDrafts,
  contactIssue,
  contactIssues,
} from '../../schemas/serviceContactChanges'
import type { OrderService } from '../../types/orderDetail'

/** Clé des erreurs de contacts : le dialogue n'édite qu'un service à la fois. */
export const SERVICE_KEY = 'service'

const NO_ISSUES: OrderErrorReport = { issues: [], stepsInError: [], message: null }

/**
 * Contacts d'un service dans son dialogue de modification.
 *
 * Tient deux listes : les contacts tels qu'enregistrés et tels que saisis.
 * Après un échec partiel, les deux se recalent sur ce que le serveur a déjà
 * accepté — un nouvel essai ne recrée ni le service ni un contact déjà passé.
 */
export function useServiceContactsForm(service: OrderService | null) {
  const [savedId, setSavedId] = useState(service?.id ?? null)
  const [saved, setSaved] = useState(service?.contacts ?? [])
  const [drafts, setDrafts] = useState(() => contactDrafts(service?.contacts ?? []))
  const [report, setReport] = useState<OrderErrorReport>(NO_ISSUES)

  /** Contrôle avant envoi ; faux s'il reste un contact à corriger. */
  const validate = (): boolean => {
    const issues = contactIssues(SERVICE_KEY, drafts)
    setReport({ ...NO_ISSUES, issues })

    return issues.length === 0
  }

  /**
   * Reprend l'avancement d'un échec, et pose l'erreur sur le contact fautif.
   * Renvoie le message à afficher en bandeau quand aucun contact n'est visé.
   */
  const absorb = (error: ServiceContactsError): string | null => {
    setSavedId(error.progress.serviceId)
    setSaved(error.progress.saved)
    setDrafts(error.progress.drafts)

    const { reason, contactIndex } = error

    if (reason instanceof ApiError && reason.isValidation && contactIndex !== null) {
      const issues = Object.entries(reason.errors).map(([field, messages]) =>
        contactIssue(SERVICE_KEY, contactIndex, field, messages[0] ?? reason.message),
      )
      setReport({ ...NO_ISSUES, issues })

      return null
    }

    return reason instanceof ApiError ? reason.message : ''
  }

  return { savedId, saved, drafts, setDrafts, report, validate, absorb }
}
