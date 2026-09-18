import { useTranslation } from 'react-i18next'

import { formatAuditValue } from '@/modules/audit/types/auditLog'
import { useStatusOptions } from '@/modules/statuses/hooks/useStatuses'
import { formatDateTime } from '@/shared/utils/format'

import type { Claim } from '../types/claim'

/** Colonne du journal → libellé déjà traduit sous `claims.fields`. */
const FIELDS: Record<string, string> = {
  title: 'title',
  claim_type: 'claimType',
  status: 'status',
  description: 'description',
  cause: 'cause',
  customer_id: 'customer',
  order_id: 'order',
  order_service_id: 'orderService',
  tour_id: 'tour',
  responsible_user_id: 'responsible',
  decision: 'decision',
  follow_up: 'followUp',
  result: 'result',
  cost: 'cost',
  closed_at: 'closedAt',
}

const fullName = (user: { firstName: string; lastName: string } | undefined) =>
  user === undefined ? undefined : `${user.firstName} ${user.lastName}`.trim()

/**
 * Ce que l'historique d'une réclamation montre à la place du journal brut.
 *
 * Le journal enregistre des colonnes et des identifiants : `customer_id →
 * 01M1H7…` ne dit rien à personne. Chaque identifiant est nommé par ce que la
 * fiche connaît déjà — le client, la commande, le service, la tournée, les
 * personnes. Un identifiant qu'elle ne connaît plus (une ancienne commande,
 * un ancien responsable) est dit indisponible plutôt que montré tel quel.
 */
export function useClaimHistoryFormat(claim: Claim | null) {
  const { t } = useTranslation()
  const { statuses } = useStatusOptions(claim === null ? '' : 'claim')

  const known: Record<string, Record<string, string | undefined>> = {
    customer_id: {
      [claim?.customerId ?? '']: claim?.customer?.name ?? claim?.customerName ?? undefined,
    },
    order_id: { [claim?.order?.id ?? '']: claim?.order?.orderNumber },
    order_service_id: { [claim?.orderService?.id ?? '']: claim?.orderService?.serviceNumber },
    tour_id: { [claim?.tour?.id ?? '']: claim?.tour?.tourNumber },
    responsible_user_id: {
      [claim?.responsibleUser?.id ?? '']: fullName(claim?.responsibleUser),
      [claim?.creator?.id ?? '']: fullName(claim?.creator),
    },
  }

  const fieldLabel = (key: string) =>
    FIELDS[key] === undefined ? key : t(`claims.fields.${FIELDS[key]}`)

  const formatValue = (key: string, value: unknown): string => {
    if (value === null || value === undefined || value === '') return '—'

    const references = known[key]
    if (references !== undefined) {
      return references[String(value)] ?? t('claims.unknownReference')
    }

    if (key === 'status') {
      const code = String(value)
      return (
        statuses.find((status) => status.code === code)?.label ??
        t(`status.${code.toLowerCase()}`, { defaultValue: code })
      )
    }

    if (key === 'closed_at') return formatDateTime(String(value))

    return formatAuditValue(value)
  }

  return { fieldLabel, formatValue }
}
