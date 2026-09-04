import { useTranslation } from 'react-i18next'

import { COMMUNICATION_CHANNELS } from '@/modules/communications/types/communication'
import { useCustomerOptions } from '@/modules/orders/hooks/useOrderScope'
import { SearchInput } from '@/shared/components/data/SearchInput'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

import { GLOBAL_SCOPE, type TemplateFilters } from '../api/templates.api'
import { TEMPLATE_TYPES } from '../types/template'

interface TemplateFilterBarProps {
  filters: TemplateFilters
  onChange: (patch: Partial<TemplateFilters>) => void
}

const ALL = 'all'

/**
 * Filtres de la liste unique des modèles.
 *
 * Un seul écran sert les messages et les factures ; sans ces filtres, un
 * comptable cherchant sa mise en page de facture la trouverait au milieu des
 * modèles de SMS.
 *
 * Le client offre trois réponses, pas deux : tous, **ceux du transporteur**, ou
 * ceux d'un client précis. Sans la valeur du milieu, on ne saurait pas isoler
 * les modèles globaux.
 */
export function TemplateFilterBar({ filters, onChange }: TemplateFilterBarProps) {
  const { t } = useTranslation()
  const customers = useCustomerOptions('')

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
      <SearchInput
        value={filters.search ?? ''}
        onChange={(search) => onChange({ page: 1, search: search || undefined })}
      />

      <Select
        value={filters.templateType ?? ALL}
        onValueChange={(value) =>
          onChange({ page: 1, templateType: value === ALL ? undefined : value })
        }
      >
        <SelectTrigger className="w-full sm:w-56" aria-label={t('templates.fields.templateType')}>
          <SelectValue placeholder={t('templates.fields.templateType')} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>{t('templates.allTypes')}</SelectItem>
          {TEMPLATE_TYPES.map((type) => (
            <SelectItem key={type} value={type}>
              {t(`templateTypes.${type}`)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Select
        value={filters.customerId ?? ALL}
        onValueChange={(value) =>
          onChange({ page: 1, customerId: value === ALL ? undefined : value })
        }
      >
        <SelectTrigger className="w-full sm:w-56" aria-label={t('templates.fields.customer')}>
          <SelectValue placeholder={t('templates.fields.customer')} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>{t('templates.allCustomers')}</SelectItem>
          <SelectItem value={GLOBAL_SCOPE}>{t('templates.globalOnly')}</SelectItem>
          {customers.options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Select
        value={filters.channel ?? ALL}
        onValueChange={(value) => onChange({ page: 1, channel: value === ALL ? undefined : value })}
      >
        <SelectTrigger className="w-full sm:w-48" aria-label={t('templates.fields.channel')}>
          <SelectValue placeholder={t('templates.fields.channel')} />
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
        value={filters.isActive === undefined ? ALL : String(filters.isActive)}
        onValueChange={(value) =>
          onChange({ page: 1, isActive: value === ALL ? undefined : value === 'true' })
        }
      >
        <SelectTrigger className="w-full sm:w-40" aria-label={t('templates.fields.isActive')}>
          <SelectValue placeholder={t('templates.fields.isActive')} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>{t('templates.allStates')}</SelectItem>
          <SelectItem value="true">{t('common.active')}</SelectItem>
          <SelectItem value="false">{t('common.inactive')}</SelectItem>
        </SelectContent>
      </Select>
    </div>
  )
}
