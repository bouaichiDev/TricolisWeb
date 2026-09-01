import { useTranslation } from 'react-i18next'

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

import type { CommunicationRuleFilters } from '../api/communication-rules.api'
import { COMMUNICATION_EVENT_TYPES, RECIPIENT_ROLES } from '../types/communication'

interface RuleFilterBarProps {
  filters: CommunicationRuleFilters
  onChange: (patch: Partial<CommunicationRuleFilters>) => void
}

const ALL = 'all'

/**
 * Filtres de la liste des règles.
 *
 * Pas de recherche textuelle : le serveur ne cherche que dans `delay_unit`,
 * ce qui n'apprendrait rien. Les trois questions utiles sont l'événement, le
 * destinataire et l'état.
 */
export function CommunicationRuleFilterBar({ filters, onChange }: RuleFilterBarProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
      <Select
        value={filters.eventType ?? ALL}
        onValueChange={(value) =>
          onChange({ page: 1, eventType: value === ALL ? undefined : value })
        }
      >
        <SelectTrigger
          className="w-full sm:w-64"
          aria-label={t('communicationRules.fields.eventType')}
        >
          <SelectValue placeholder={t('communicationRules.fields.eventType')} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>{t('communicationRules.allEvents')}</SelectItem>
          {COMMUNICATION_EVENT_TYPES.map((event) => (
            <SelectItem key={event} value={event}>
              {t(`communicationEvents.${event}`)}
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
          aria-label={t('communicationRules.fields.recipientRole')}
        >
          <SelectValue placeholder={t('communicationRules.fields.recipientRole')} />
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

      <Select
        value={filters.isActive === undefined ? ALL : String(filters.isActive)}
        onValueChange={(value) =>
          onChange({ page: 1, isActive: value === ALL ? undefined : value === 'true' })
        }
      >
        <SelectTrigger
          className="w-full sm:w-40"
          aria-label={t('communicationRules.fields.isActive')}
        >
          <SelectValue placeholder={t('communicationRules.fields.isActive')} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>{t('templates.allStates')}</SelectItem>
          <SelectItem value="true">{t('common.active')}</SelectItem>
          <SelectItem value="false">{t('common.inactive')}</SelectItem>
        </SelectContent>
      </Select>

      <Select
        value={filters.isAutomatic === undefined ? ALL : String(filters.isAutomatic)}
        onValueChange={(value) =>
          onChange({ page: 1, isAutomatic: value === ALL ? undefined : value === 'true' })
        }
      >
        <SelectTrigger
          className="w-full sm:w-48"
          aria-label={t('communicationRules.fields.isAutomatic')}
        >
          <SelectValue placeholder={t('communicationRules.fields.isAutomatic')} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>{t('communicationRules.allModes')}</SelectItem>
          <SelectItem value="true">{t('communicationRules.automaticOnly')}</SelectItem>
          <SelectItem value="false">{t('communicationRules.manualOnlyFilter')}</SelectItem>
        </SelectContent>
      </Select>
    </div>
  )
}
