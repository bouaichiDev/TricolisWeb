import { useTranslation } from 'react-i18next'

import { ControlledField } from '@/shared/components/form/ControlledField'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'

import { hasSubject } from '../types/communication'

interface MessageValue {
  subject: string
  body: string
  scheduledAt: string
}

interface CommunicationMessageFieldsProps {
  channel: string
  value: MessageValue
  onChange: (patch: Partial<MessageValue>) => void
}

/**
 * Contenu du message, prérempli par le modèle.
 *
 * L'avertissement n'est pas décoratif : **aucun endpoint de rendu n'existe**.
 * Ce qui est affiché ici est le modèle tel quel, `{{orderNumber}}` compris, et
 * la substitution aura lieu au départ, côté serveur. Sans cette phrase, un
 * utilisateur croirait le message incomplet et corrigerait à la main ce que le
 * serveur allait remplir.
 *
 * `scheduledAt` **est** la programmation : il n'y a pas de route `schedule`, et
 * poser une date fait passer le brouillon en `scheduled`.
 */
export function CommunicationMessageFields({
  channel,
  value,
  onChange,
}: CommunicationMessageFieldsProps) {
  const { t } = useTranslation()

  return (
    <>
      <Alert>
        <AlertDescription>{t('communications.noRenderHint')}</AlertDescription>
      </Alert>

      {hasSubject(channel) ? (
        <ControlledField
          label={t('communications.fields.subject')}
          value={value.subject}
          onChange={(subject) => onChange({ subject })}
        />
      ) : null}

      <ControlledField
        label={t('communications.fields.body')}
        value={value.body}
        onChange={(body) => onChange({ body })}
        multiline
      />

      <ControlledField
        label={t('communications.fields.scheduledAt')}
        type="datetime-local"
        value={value.scheduledAt}
        onChange={(scheduledAt) => onChange({ scheduledAt })}
        description={t('communications.scheduleHint')}
      />
    </>
  )
}
