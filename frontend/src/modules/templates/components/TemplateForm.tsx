import { useTranslation } from 'react-i18next'

import { COMMUNICATION_CHANNELS, hasSubject } from '@/modules/communications/types/communication'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { Label } from '@/shared/components/ui/label'
import { Switch } from '@/shared/components/ui/switch'

import { TemplateScopeFields } from './TemplateScopeFields'
import { TemplateVariablePicker } from './TemplateVariablePicker'
import type { TemplateFormValues } from '../schemas/templateSchema'
import { BODY_FORMATS, TEMPLATE_TYPES, isDocumentType } from '../types/template'
import {
  INVOICE_PATHS,
  INVOICE_STARTER_BODY,
  INVOICE_STARTER_VARIABLES,
} from '../utils/invoiceVariables'

interface TemplateFormProps {
  values: TemplateFormValues
  onChange: (patch: Partial<TemplateFormValues>) => void
  /** Faux en modification : `code` identifie le modèle, il ne bouge pas. */
  codeEditable: boolean
}

/**
 * Formulaire d'un modèle — message ou document.
 *
 * **Le type gouverne la forme.** Choisir `invoice` retire canal, objet et
 * service : une facture est un document, et le serveur refuse les trois. Les
 * laisser visibles ferait saisir ce qui sera rejeté.
 *
 * **Le sujet suit le canal** : le serveur ne l'exige que pour l'e-mail, et un
 * SMS n'en a pas.
 *
 * `availableVariables` déclare ce que le modèle sait recevoir. Pour un message,
 * la liste est libre — aucun contexte canonique n'existe. Pour une facture,
 * elle est proposée depuis le contexte réel du serveur : déclarer un chemin
 * qu'il ne fournit pas ferait échouer le rendu à la clôture.
 */
export function TemplateForm({ values, onChange, codeEditable }: TemplateFormProps) {
  const { t } = useTranslation()

  const document = isDocumentType(values.templateType)
  const subjectRequired = !document && hasSubject(values.channel)

  /**
   * Passer en facture repart d'une mise en page utilisable.
   *
   * Une page blanche obligerait à écrire un document complet en devinant les
   * chemins — le meilleur moyen d'obtenir un rendu en échec à la clôture.
   */
  const changeType = (templateType: string) => {
    if (!isDocumentType(templateType)) {
      onChange({ templateType })

      return
    }

    onChange({
      templateType,
      channel: '',
      serviceId: '',
      subjectTemplate: '',
      bodyFormat: 'html',
      bodyTemplate: values.bodyTemplate.trim() === '' ? INVOICE_STARTER_BODY : values.bodyTemplate,
      availableVariables:
        values.availableVariables.length === 0
          ? INVOICE_STARTER_VARIABLES
          : values.availableVariables,
    })
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <ControlledField
          label={t('templates.fields.code')}
          value={values.code}
          onChange={(code) => onChange({ code })}
          required
          disabled={!codeEditable}
          description={codeEditable ? t('templates.codeHint') : t('templates.codeLocked')}
        />

        <ControlledField
          label={t('templates.fields.name')}
          value={values.name}
          onChange={(name) => onChange({ name })}
          required
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <AsyncSelect
          label={t('templates.fields.templateType')}
          value={values.templateType}
          onChange={changeType}
          options={TEMPLATE_TYPES.map((type) => ({
            value: type,
            label: t(`templateTypes.${type}`),
          }))}
          required
          description={t('templates.typeHint')}
        />

        {document ? null : (
          <AsyncSelect
            label={t('templates.fields.channel')}
            value={values.channel}
            onChange={(channel) => onChange({ channel })}
            options={COMMUNICATION_CHANNELS.map((channel) => ({
              value: channel,
              label: t(`communicationChannels.${channel}`),
            }))}
            required
          />
        )}

        <ControlledField
          label={t('templates.fields.language')}
          value={values.language}
          onChange={(language) => onChange({ language })}
          required
          description={t('templates.languageHint')}
        />
      </div>

      <TemplateScopeFields values={values} onChange={onChange} />

      {subjectRequired ? (
        <ControlledField
          label={t('templates.fields.subjectTemplate')}
          value={values.subjectTemplate}
          onChange={(subjectTemplate) => onChange({ subjectTemplate })}
          required
        />
      ) : null}

      {/* Le SMS ne connait que le texte : proposer HTML y serait un piege. */}
      {subjectRequired || document ? (
        <AsyncSelect
          label={t('templates.fields.bodyFormat')}
          value={values.bodyFormat}
          onChange={(bodyFormat) => onChange({ bodyFormat })}
          options={BODY_FORMATS.map((format) => ({
            value: format,
            label: t(`bodyFormats.${format}`),
          }))}
          description={t('templates.bodyFormatHint')}
        />
      ) : null}

      <ControlledField
        label={t('templates.fields.bodyTemplate')}
        value={values.bodyTemplate}
        onChange={(bodyTemplate) => onChange({ bodyTemplate })}
        required
        multiline
        description={document ? t('templates.invoiceBodyHint') : undefined}
      />

      <TemplateVariablePicker
        variables={values.availableVariables}
        onChange={(availableVariables) => onChange({ availableVariables })}
        suggestions={document ? INVOICE_PATHS : []}
      />

      <div className="flex flex-wrap gap-6 border-t pt-4">
        <span className="flex items-center gap-2">
          <Switch
            id="template-active"
            checked={values.isActive}
            onCheckedChange={(isActive) => onChange({ isActive })}
          />
          <Label htmlFor="template-active">{t('templates.fields.isActive')}</Label>
        </span>

        <span className="flex items-center gap-2">
          <Switch
            id="template-default"
            checked={values.isDefault}
            onCheckedChange={(isDefault) => onChange({ isDefault })}
          />
          <Label htmlFor="template-default">{t('templates.fields.isDefault')}</Label>
        </span>
      </div>
    </div>
  )
}
