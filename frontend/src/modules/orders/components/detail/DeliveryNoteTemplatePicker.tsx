import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'

import type { DeliveryNoteTemplateOption } from '../../api/deliveryNotes.api'

interface DeliveryNoteTemplatePickerProps {
  templates: DeliveryNoteTemplateOption[]
  /** Le modèle retenu — présélectionné sur celui que le serveur emploierait. */
  value: string | undefined
  onChange: (templateId: string) => void
}

/**
 * Le choix du modèle de BL, quand il y en a plusieurs.
 *
 * **Un seul modèle : aucune question.** Le serveur l'emploierait de toute façon,
 * et un menu à une entrée n'apprend rien — il donne juste un clic de plus avant
 * un document qu'on attend.
 *
 * Chaque entrée dit **pourquoi** elle est là : le modèle propre au client, ou
 * celui du transporteur. Sans cette mention, voyant la mise en page globale
 * s'afficher, on ne saurait pas si le modèle du client a été ignoré ou s'il n'a
 * jamais été créé.
 */
export function DeliveryNoteTemplatePicker({
  templates,
  value,
  onChange,
}: DeliveryNoteTemplatePickerProps) {
  const { t } = useTranslation()

  if (templates.length < 2) return null

  return (
    <AsyncSelect
      label={t('orders.deliveryNote.template')}
      value={value ?? ''}
      onChange={onChange}
      options={templates.map((template) => ({
        value: template.id,
        label: template.name,
        hint: t(`orders.deliveryNote.scopes.${template.scope}`),
      }))}
      description={t('orders.deliveryNote.templateHint')}
    />
  )
}
