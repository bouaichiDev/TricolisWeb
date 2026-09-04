import { useTranslation } from 'react-i18next'

import { SearchInput } from '@/shared/components/data/SearchInput'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

import type { OrderCommunicationFilters } from '../api/order-communications.api'
import {
  COMMUNICATION_CHANNELS,
  COMMUNICATION_STATUSES,
  RECIPIENT_ROLES,
} from '../types/communication'

interface CommunicationFilterBarProps {
  filters: OrderCommunicationFilters
  onChange: (patch: Partial<OrderCommunicationFilters>) => void
  /** Le statut est imposé par l'onglet : le laisser modifiable le contredirait. */
  statusLocked?: boolean
}

const ALL = 'all'

/**
 * Filtres de l'historique des communications.
 *
 * La recherche porte sur le destinataire, l'objet, le corps, l'identifiant
 * fournisseur et le message d'erreur — ce que `CommunicationListQuery` déclare
 * cherchable, pas davantage.
 */
export function CommunicationFilterBar({
  filters,
  onChange,
  statusLocked = false,
}: CommunicationFilterBarProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
      <SearchInput
        value={filters.search ?? ''}
        onChange={(search) => onChange({ page: 1, search: search || undefined })}
      />

      {statusLocked ? null : (
        <Select
          value={filters.status ?? ALL}
          onValueChange={(value) => onChange({ page: 1, status: value === ALL ? undefined : value })}
        >
          <SelectTrigger className="w-full sm:w-48" aria-label={t('communications.fields.status')}>
            <SelectValue placeholder={t('communications.fields.status')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>{t('communications.allStatuses')}</SelectItem>
            {COMMUNICATION_STATUSES.map((status) => (
              <SelectItem key={status} value={status}>
                {t(`communicationStatuses.${status}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      <Select
        value={filters.channel ?? ALL}
        onValueChange={(value) => onChange({ page: 1, channel: value === ALL ? undefined : value })}
      >
        <SelectTrigger className="w-full sm:w-48" aria-label={t('communications.fields.channel')}>
          <SelectValue placeholder={t('communications.fields.channel')} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>{t('templates.allChannels')}</SelectItem>
          {COMMUNICATION_CHANNELS.map((channel) => (
            <SelectItem key={channel} value={channel}>
              {t(`communicationChannels.${channel}`)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Select
        value={filters.recipientRole ?? ALL}
        onValueChange={(value) =>
          onChange({ page: 1, recipientRole: value === ALL ? undefined : value })
        }
      >
        <SelectTrigger
          className="w-full sm:w-56"
          aria-label={t('communications.fields.recipientRole')}
        >
          <SelectValue placeholder={t('communications.fields.recipientRole')} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>{t('communicationRules.allRecipients')}</SelectItem>
          {RECIPIENT_ROLES.map((role) => (
            <SelectItem key={role} value={role}>
              {t(`recipientRoles.${role}`)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )
}
