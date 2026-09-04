import { useTranslation } from 'react-i18next'

import { useStatusList, useStatusSources } from '@/modules/statuses/hooks/useStatuses'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'

interface JourneyTriggerFieldsProps {
  sourceType: string
  statusCode: string
  onChange: (patch: { sourceType?: string; statusCode?: string }) => void
  enabled: boolean
}

/**
 * Le déclencheur d'une étape : quelle table, quel statut.
 *
 * Les deux viennent des **référentiels existants** — les sources dérivées de la
 * morph map, puis les statuts décrits pour l'entité choisie. Saisir un statut à
 * la main créerait une étape que rien ne déclencherait jamais, et le serveur
 * valide de toute façon l'entité contre la morph map.
 *
 * Changer d'entité vide le statut : celui d'avant décrirait un déclencheur qui
 * n'existe pas sur la nouvelle table.
 */
export function JourneyTriggerFields({
  sourceType,
  statusCode,
  onChange,
  enabled,
}: JourneyTriggerFieldsProps) {
  const { t } = useTranslation()

  const sources = useStatusSources()
  const statuses = useStatusList(
    { page: 1, perPage: 100, source: sourceType, sort: 'position', direction: 'asc' },
    enabled && sourceType !== '',
  )

  const noStatus =
    sourceType !== '' && !statuses.isPending && (statuses.data?.data ?? []).length === 0

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <AsyncSelect
        label={t('journey.fields.sourceType')}
        value={sourceType}
        onChange={(next) => onChange({ sourceType: next, statusCode: '' })}
        options={(sources.data ?? []).map((source) => ({ value: source, label: source }))}
        isLoading={sources.isPending}
        required
        description={t('journey.sourceHint')}
      />

      <AsyncSelect
        label={t('journey.fields.statusCode')}
        value={statusCode}
        onChange={(next) => onChange({ statusCode: next })}
        options={(statuses.data?.data ?? []).map((status) => ({
          value: status.code,
          label: status.label,
          hint: status.code,
        }))}
        isLoading={statuses.isPending}
        disabled={sourceType === ''}
        required
        description={
          sourceType === ''
            ? t('journey.pickSourceFirst')
            : noStatus
              ? t('journey.noStatus')
              : undefined
        }
      />
    </div>
  )
}
