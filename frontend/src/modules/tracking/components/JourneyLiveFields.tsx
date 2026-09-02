import { useTranslation } from 'react-i18next'

import { useApiConfigurationList } from '@/modules/integrations/hooks/useApiConfigurations'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'

/** Valeur désignant « aucune API », Radix refusant une option vide. */
export const NO_API = 'none'

interface JourneyLiveFieldsProps {
  apiConfigurationId: string
  onChange: (apiConfigurationId: string) => void
  /** Les API ne se chargent qu'à l'ouverture de la fenêtre. */
  enabled: boolean
}

/**
 * L'API qui renseigne la position, quand l'étape se suit en direct.
 *
 * Non nulle, elle dit **laquelle** : un simple drapeau « suivi en direct »
 * aurait laissé la question ouverte, et une étape marquée vivante sans source
 * n'aurait rien à montrer sur la carte.
 */
export function JourneyLiveFields({
  apiConfigurationId,
  onChange,
  enabled,
}: JourneyLiveFieldsProps) {
  const { t } = useTranslation()

  const apis = useApiConfigurationList({ page: 1, perPage: 100 }, enabled)

  return (
    <AsyncSelect
      label={t('journey.fields.api')}
      value={apiConfigurationId}
      onChange={onChange}
      options={[
        { value: NO_API, label: t('journey.noApi') },
        ...(apis.data?.data ?? []).map((api) => ({
          value: api.id,
          label: api.name,
          hint: api.code,
        })),
      ]}
      isLoading={apis.isPending}
      description={t('journey.apiHint')}
    />
  )
}
