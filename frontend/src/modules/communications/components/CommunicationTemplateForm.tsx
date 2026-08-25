import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { Switch } from '@/shared/components/ui/switch'
import { Label } from '@/shared/components/ui/label'

import { TemplateVariablePicker } from './TemplateVariablePicker'
import {
  COMMUNICATION_CHANNELS,
  COMMUNICATION_TEMPLATE_TYPES,
  hasSubject,
} from '../types/communication'
import type { TemplateFormValues } from '../schemas/templateForm'

interface CommunicationTemplateFormProps {
  values: TemplateFormValues
  onChange: (patch: Partial<TemplateFormValues>) => void
  /** Faux en modification : `code` identifie le modèle, il ne bouge pas. */
  codeEditable: boolean
}

/**
 * Formulaire d'un modèle de message.
 *
 * Le **sujet suit le canal** : `StoreCommunicationTemplateRequest` le rend
 * requis pour un e-mail par `Rule::requiredIf`, et un SMS n'en a pas. Le
 * demander toujours ferait saisir une valeur que le canal n'emploiera jamais.
 *
 * `availableVariables` déclare ce que le modèle sait recevoir. La liste vient du
 * modèle lui-même, pas d'une table écrite ici : le §23 l'exige, et une liste
 * inventée divergerait de ce que le serveur substitue réellement.
 */
export function CommunicationTemplateForm({
  values,
  onChange,
  codeEditable,
}: CommunicationTemplateFormProps) {
  const { t } = useTranslation()
  const subjectRequired = hasSubject(values.channel)

  return (
    <div className="flex flex-col gap-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <ControlledField
          label={t('communicationTemplates.fields.code')}
          value={values.code}
          onChange={(code) => onChange({ code })}
          required
          disabled={!codeEditable}
          description={
            codeEditable
              ? t('communicationTemplates.codeHint')
              : t('communicationTemplates.codeLocked')
          }
        />

        <ControlledField
          label={t('communicationTemplates.fields.name')}
          value={values.name}
          onChange={(name) => onChange({ name })}
          required
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <AsyncSelect
          label={t('communicationTemplates.fields.channel')}
          value={values.channel}
          onChange={(channel) => onChange({ channel })}
          options={COMMUNICATION_CHANNELS.map((channel) => ({
            value: channel,
            label: t(`communicationChannels.${channel}`),
          }))}
          required
        />

        <AsyncSelect
          label={t('communicationTemplates.fields.templateType')}
          value={values.templateType}
          onChange={(templateType) => onChange({ templateType })}
          options={COMMUNICATION_TEMPLATE_TYPES.map((type) => ({
            value: type,
            label: t(`communicationTemplateTypes.${type}`),
          }))}
          required
          description={t('communicationTemplates.typeHint')}
        />

        <ControlledField
          label={t('communicationTemplates.fields.language')}
          value={values.language}
          onChange={(language) => onChange({ language })}
          required
          description={t('communicationTemplates.languageHint')}
        />
      </div>

      {subjectRequired ? (
        <ControlledField
          label={t('communicationTemplates.fields.subjectTemplate')}
          value={values.subjectTemplate}
          onChange={(subjectTemplate) => onChange({ subjectTemplate })}
          required
        />
      ) : null}

      <ControlledField
        label={t('communicationTemplates.fields.bodyTemplate')}
        value={values.bodyTemplate}
        onChange={(bodyTemplate) => onChange({ bodyTemplate })}
        required
        multiline
      />

      <TemplateVariablePicker
        variables={values.availableVariables}
        onChange={(availableVariables) => onChange({ availableVariables })}
      />

      <div className="flex flex-wrap gap-6 border-t pt-4">
        <span className="flex items-center gap-2">
          <Switch
            id="template-active"
            checked={values.isActive}
            onCheckedChange={(isActive) => onChange({ isActive })}
          />
          <Label htmlFor="template-active">{t('communicationTemplates.fields.isActive')}</Label>
        </span>

        <span className="flex items-center gap-2">
          <Switch
            id="template-default"
            checked={values.isDefault}
            onCheckedChange={(isDefault) => onChange({ isDefault })}
          />
          <Label htmlFor="template-default">{t('communicationTemplates.fields.isDefault')}</Label>
        </span>
      </div>
    </div>
  )
}
