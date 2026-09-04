import { useTranslation } from 'react-i18next'

import { ControlledField } from '@/shared/components/form/ControlledField'

import type { ApiCallSettings } from '../types/apiConfiguration'

interface ApiCallFieldsProps {
  value: Required<{ [K in keyof ApiCallSettings]: string }>
  onChange: (patch: Partial<Record<keyof ApiCallSettings, string>>) => void
}

/**
 * La forme de l'appel : chemin et paramètres.
 *
 * Décrite ici plutôt que codée, parce qu'aucun fournisseur ne ressemble au
 * précédent. Chez Flespi, le chemin porte le **canal de l'organisme**, fixe, et
 * le filtre porte la **référence de la course**, variable — les confondre
 * n'interroge rien.
 *
 * Deux jetons seulement sont reconnus : `{reference}` et `{limit}`.
 */
export function ApiCallFields({ value, onChange }: ApiCallFieldsProps) {
  const { t } = useTranslation()

  return (
    <section className="flex flex-col gap-4 border-t pt-4">
      <div>
        <p className="text-sm font-medium">{t('apiConfigurations.callSection')}</p>
        <p className="text-xs text-muted-foreground">{t('apiConfigurations.callHint')}</p>
      </div>

      <ControlledField
        label={t('apiConfigurations.fields.path')}
        value={value.path}
        onChange={(path) => onChange({ path })}
        placeholder={t('apiConfigurations.pathPlaceholder')}
      />

      <div className="grid gap-4 sm:grid-cols-3">
        <ControlledField
          label={t('apiConfigurations.fields.queryKey')}
          value={value.queryKey}
          onChange={(queryKey) => onChange({ queryKey })}
          placeholder="data"
        />

        <div className="sm:col-span-2">
          <ControlledField
            label={t('apiConfigurations.fields.queryTemplate')}
            value={value.queryTemplate}
            onChange={(queryTemplate) => onChange({ queryTemplate })}
            placeholder={t('apiConfigurations.queryTemplatePlaceholder')}
          />
        </div>
      </div>
    </section>
  )
}
