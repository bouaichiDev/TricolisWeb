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
import { TEMPLATE_CATEGORIES, type TemplateCategory, typesInCategory } from '../types/template'

interface TemplateFilterBarProps {
  filters: TemplateFilters
  onChange: (patch: Partial<TemplateFilters>) => void
}

const ALL = 'all'

/**
 * Filtres de la liste unique des modèles.
 *
 * Un seul écran sert les messages, les factures et les bons de livraison ; sans
 * ces filtres, un comptable cherchant sa mise en page de facture la trouverait
 * au milieu des modèles de SMS.
 *
 * **La catégorie commande le reste.** Choisir un rayon restreint la liste des
 * types à ceux qu'il contient, et retire le filtre de canal quand le rayon n'en
 * a pas — un document ne part par aucun canal, et proposer « SMS » sur les BL
 * serait un filtre qui ne rend jamais rien.
 *
 * Le client offre trois réponses, pas deux : tous, **ceux du transporteur**, ou
 * ceux d'un client précis. Sans la valeur du milieu, on ne saurait pas isoler
 * les modèles globaux.
 */
export function TemplateFilterBar({ filters, onChange }: TemplateFilterBarProps) {
  const { t } = useTranslation()
  const customers = useCustomerOptions('')

  const category = filters.category as TemplateCategory | undefined
  const types = typesInCategory(category)
  const showsChannel = category === undefined || category === 'communication'

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
      <SearchInput
        value={filters.search ?? ''}
        onChange={(search) => onChange({ page: 1, search: search || undefined })}
      />

      <Select
        value={filters.category ?? ALL}
        onValueChange={(value) =>
          onChange({
            page: 1,
            category: value === ALL ? undefined : value,
            // Le type retenu appartenait au rayon precedent : le garder
            // afficherait une liste vide sans dire pourquoi.
            templateType: undefined,
            channel: undefined,
          })
        }
      >
        <SelectTrigger className="w-full sm:w-52" aria-label={t('templates.fields.category')}>
          <SelectValue placeholder={t('templates.fields.category')} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>{t('templates.allCategories')}</SelectItem>
          {TEMPLATE_CATEGORIES.map((value) => (
            <SelectItem key={value} value={value}>
              {t(`templateCategories.${value}`)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {types.length > 1 ? (
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
            {types.map((type) => (
              <SelectItem key={type} value={type}>
                {t(`templateTypes.${type}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      ) : null}

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

      {showsChannel ? (
        <Select
          value={filters.channel ?? ALL}
          onValueChange={(value) =>
            onChange({ page: 1, channel: value === ALL ? undefined : value })
          }
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
      ) : null}

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
