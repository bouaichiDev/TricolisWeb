import { useTranslation } from 'react-i18next'

import { useActiveServices } from '@/modules/services/hooks/useServices'
import { useTemplateOptions } from '@/modules/templates/hooks/useTemplates'
import type { Template } from '@/modules/templates/types/template'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { Switch } from '@/shared/components/ui/switch'

import { CommunicationRuleConditionsBuilder } from './CommunicationRuleConditionsBuilder'
import { CommunicationRuleSummary } from './CommunicationRuleSummary'
import { COMMUNICATION_EVENT_TYPES, RECIPIENT_ROLES } from '../types/communication'
import { DELAY_UNITS } from '../types/communicationRule'
import type { RuleFormValues } from '../schemas/ruleSchema'

interface RuleFormProps {
  values: RuleFormValues
  onChange: (patch: Partial<RuleFormValues>) => void
}

const NONE = '__none__'

/**
 * Formulaire d'une règle de communication.
 *
 * **Le destinataire `custom` n'est pas proposé.** Une règle automatique doit
 * résoudre son destinataire depuis la commande ; `custom` n'a aucune source
 * pour cela, et la règle ne produirait jamais rien. Le §69 demande de ne pas
 * autoriser une automatisation impossible à résoudre.
 *
 * **Les modèles proposés excluent les documents.** Une facture n'a pas de canal
 * par où partir, et le serveur refuserait la règle.
 *
 * Le **canal n'est pas saisi** : il vient du modèle choisi. Le §158 l'interdit
 * sur la règle, et le résumé le rappelle en le lisant depuis le modèle.
 */
export function CommunicationRuleForm({ values, onChange }: RuleFormProps) {
  const { t } = useTranslation()

  const services = useActiveServices()
  const templates = useTemplateOptions()

  // Les documents sont ecartes : une facture n'a pas de canal par ou partir,
  // et le serveur refuserait la regle. Le predicat retire aussi `null` du type
  // du canal, ce qui evite un libelle « communicationChannels.null ».
  const selectable = (templates.data?.data ?? []).filter(
    (template): template is Template & { channel: NonNullable<Template['channel']> } =>
      template.channel !== null,
  )
  const template = selectable.find((item) => item.id === values.templateId)
  const serviceName = (services.data?.data ?? []).find(
    (item) => item.id === values.serviceId,
  )?.name

  return (
    <div className="flex flex-col gap-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <AsyncSelect
          label={t('communicationRules.fields.eventType')}
          value={values.eventType}
          onChange={(eventType) => onChange({ eventType })}
          options={COMMUNICATION_EVENT_TYPES.map((event) => ({
            value: event,
            label: t(`communicationEvents.${event}`),
          }))}
          required
          description={t('communicationRules.eventHint')}
        />

        <AsyncSelect
          label={t('communicationRules.fields.service')}
          value={values.serviceId === '' ? NONE : values.serviceId}
          onChange={(serviceId) => onChange({ serviceId: serviceId === NONE ? '' : serviceId })}
          options={[
            { value: NONE, label: t('communicationRules.allServices') },
            ...(services.data?.data ?? []).map((service) => ({
              value: service.id,
              label: service.name,
              hint: service.code,
            })),
          ]}
          isLoading={services.isPending}
          description={t('communicationRules.serviceHint')}
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <AsyncSelect
          label={t('communicationRules.fields.template')}
          value={values.templateId}
          onChange={(templateId) => onChange({ templateId })}
          options={selectable.map((item) => ({
            value: item.id,
            label: item.name,
            hint: `${item.code} · ${t(`communicationChannels.${item.channel}`)}`,
          }))}
          isLoading={templates.isPending}
          required
          description={t('communicationRules.templateHint')}
        />

        <AsyncSelect
          label={t('communicationRules.fields.recipientRole')}
          value={values.recipientRole}
          onChange={(recipientRole) => onChange({ recipientRole })}
          options={RECIPIENT_ROLES.filter((role) => role !== 'custom').map((role) => ({
            value: role,
            label: t(`recipientRoles.${role}`),
          }))}
          required
          description={t('communicationRules.recipientHint')}
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="rule-delay">{t('communicationRules.fields.delayValue')}</Label>
          <Input
            id="rule-delay"
            type="number"
            min={0}
            max={100000}
            value={String(values.delayValue)}
            onChange={(event) => onChange({ delayValue: Number(event.target.value) || 0 })}
          />
          <p className="text-xs text-muted-foreground">{t('communicationRules.delayHint')}</p>
        </div>

        <AsyncSelect
          label={t('communicationRules.fields.delayUnit')}
          value={values.delayUnit}
          onChange={(delayUnit) =>
            onChange({ delayUnit: delayUnit as RuleFormValues['delayUnit'] })
          }
          options={DELAY_UNITS.map((unit) => ({
            value: unit,
            label: t(`communicationRules.delayUnits.${unit}`),
          }))}
          required
        />
      </div>

      <CommunicationRuleConditionsBuilder
        conditions={values.conditions}
        onChange={(conditions) => onChange({ conditions })}
      />

      <div className="flex flex-wrap gap-6 border-t pt-4">
        <span className="flex items-center gap-2">
          <Switch
            id="rule-automatic"
            checked={values.isAutomatic}
            onCheckedChange={(isAutomatic) => onChange({ isAutomatic })}
          />
          <Label htmlFor="rule-automatic">{t('communicationRules.fields.isAutomatic')}</Label>
        </span>

        <span className="flex items-center gap-2">
          <Switch
            id="rule-active"
            checked={values.isActive}
            onCheckedChange={(isActive) => onChange({ isActive })}
          />
          <Label htmlFor="rule-active">{t('communicationRules.fields.isActive')}</Label>
        </span>
      </div>

      <CommunicationRuleSummary values={values} template={template} serviceName={serviceName} />
    </div>
  )
}
