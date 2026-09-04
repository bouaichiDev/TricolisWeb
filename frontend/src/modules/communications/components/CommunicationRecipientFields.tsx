import { useTranslation } from 'react-i18next'

import { ControlledField } from '@/shared/components/form/ControlledField'

import { usesPhone } from '../types/communication'

interface RecipientValue {
  name: string
  email: string
  phone: string
}

interface CommunicationRecipientFieldsProps {
  channel: string
  value: RecipientValue
  onChange: (patch: Partial<RecipientValue>) => void
}

/**
 * Coordonnées du destinataire, selon le canal.
 *
 * Le §31 sépare les canaux : un e-mail s'adresse à une adresse, un SMS et un
 * WhatsApp à un numéro. Demander les deux à chaque fois ferait saisir une donnée
 * que le canal n'emploiera jamais.
 *
 * Rien n'est requis ici : `StoreOrderCommunicationRequest` accepte les trois en
 * `nullable`, parce que le serveur résout lui-même le destinataire à partir du
 * rôle quand il le peut. Ce qui est saisi passe outre — utile surtout pour le
 * rôle `custom`, où personne d'autre ne connaît l'adresse.
 */
export function CommunicationRecipientFields({
  channel,
  value,
  onChange,
}: CommunicationRecipientFieldsProps) {
  const { t } = useTranslation()

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <ControlledField
        label={t('communications.fields.recipientName')}
        value={value.name}
        onChange={(name) => onChange({ name })}
      />

      {usesPhone(channel) ? (
        <ControlledField
          label={t('communications.fields.recipientPhone')}
          type="tel"
          value={value.phone}
          onChange={(phone) => onChange({ phone })}
        />
      ) : (
        <ControlledField
          label={t('communications.fields.recipientEmail')}
          type="email"
          value={value.email}
          onChange={(email) => onChange({ email })}
        />
      )}
    </div>
  )
}
