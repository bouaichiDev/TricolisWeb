import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

import { CommunicationMessageFields } from './CommunicationMessageFields'
import { CommunicationRecipientFields } from './CommunicationRecipientFields'
import { useTemplate, useTemplateList } from '@/modules/templates/hooks/useTemplates'
import type { Template } from '@/modules/templates/types/template'
import { useCreateOrderCommunication } from '../hooks/useOrderCommunications'
import { TEMPLATE_TYPES } from '@/modules/templates/types/template'
import { COMMUNICATION_CHANNELS, hasSubject, RECIPIENT_ROLES } from '../types/communication'

interface CreateOrderCommunicationDialogProps {
  orderId: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

/** Valeur designant « sans modele », Radix refusant une option vide. */
const FREE_FORM = 'free'

/**
 * Nouvelle communication, à partir d'un template.
 *
 * Le contenu vient d'un `CommunicationTemplate` déjà configuré : le §29 exige
 * que le système soit **piloté par les templates**, sans bouton codé en dur par
 * scénario. « Client absent » n'est donc pas un cas particulier de cet écran,
 * c'est un template parmi d'autres.
 *
 * Choisir un template remplit le canal, le type, le sujet et le corps — **tels
 * quels**. Aucun endpoint de rendu n'existe : `{{orderNumber}}` reste visible,
 * et l'écran le dit plutôt que de simuler une substitution que le serveur ne
 * ferait pas de la même façon.
 *
 * **Un modèle n'est pas obligatoire.** `templateId` est `nullable` côté
 * serveur : un message ponctuel, qui ne se reproduira pas, n'a pas à devenir un
 * modèle de l'organisation. Sans modèle, le canal et le type se choisissent à
 * la main — c'est ce que le modèle aurait fourni.
 *
 * Le message part en **brouillon**. La mise en file est une action distincte,
 * volontairement : relire avant d'envoyer vaut mieux qu'un envoi au premier
 * clic.
 */
export function CreateOrderCommunicationDialog({
  orderId,
  open,
  onOpenChange,
}: CreateOrderCommunicationDialogProps) {
  const { t } = useTranslation()
  const create = useCreateOrderCommunication(orderId)

  const [templateId, setTemplateId] = useState(FREE_FORM)
  const [channel, setChannel] = useState('email')
  const [communicationType, setCommunicationType] = useState('custom')
  const [recipientRole, setRecipientRole] = useState('customer')
  const [recipient, setRecipient] = useState({ name: '', email: '', phone: '' })
  const [message, setMessage] = useState({ subject: '', body: '', scheduledAt: '' })
  const [error, setError] = useState<string | null>(null)

  // Seuls les templates actifs : proposer un template retire ferait partir un
  // message que l'organisation a justement decide de ne plus utiliser.
  const templates = useTemplateList(
    { page: 1, perPage: 100, isActive: true, sort: 'name', direction: 'asc' },
    open,
  )

  // Les documents sont ecartes : une facture n'a pas de canal par ou partir,
  // et le serveur refuserait la communication.
  const available = (templates.data?.data ?? []).filter(
    (template): template is Template & { channel: NonNullable<Template['channel']> } =>
      template.channel !== null,
  )
  const chosen = available.find((item) => item.id === templateId)

  // Sans modele, le canal et le type viennent des selecteurs ; avec, du modele.
  const effectiveChannel = chosen?.channel ?? channel
  const effectiveType = chosen?.templateType ?? communicationType

  // La liste ne transporte ni objet ni corps — des LONGTEXT que le §37 interdit
  // d'y charger. Les recopier depuis la ligne remplissait le message avec
  // `undefined` ; le modele choisi est donc recharge en entier.
  const full = useTemplate(templateId === FREE_FORM ? undefined : templateId)

  useEffect(() => {
    const template = full.data

    if (template === undefined) return

    setMessage((current) => ({
      ...current,
      subject: template.subjectTemplate ?? '',
      body: template.bodyTemplate ?? '',
    }))
  }, [full.data])

  const applyTemplate = (id: string) => {
    setTemplateId(id)

    if (id !== FREE_FORM) return

    // Revenir au message libre efface ce que le modele avait pose : le laisser
    // ferait passer pour une saisie ce qui venait d'ailleurs.
    setMessage((current) => ({ ...current, subject: '', body: '' }))
  }

  const submit = async () => {
    setError(null)

    try {
      await create.mutateAsync({
        templateId: chosen?.id ?? null,
        channel: effectiveChannel,
        communicationType: effectiveType,
        recipientRole,
        recipientName: blank(recipient.name),
        recipientEmail: blank(recipient.email),
        recipientPhone: blank(recipient.phone),
        subject: hasSubject(effectiveChannel) ? blank(message.subject) : null,
        body: blank(message.body),
        scheduledAt:
          message.scheduledAt === '' ? null : new Date(message.scheduledAt).toISOString(),
      })

      onOpenChange(false)
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('communications.create')}</DialogTitle>
          <DialogDescription>{t('communications.createHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <div className="flex flex-col gap-5">
          <AsyncSelect
            label={t('communications.fields.template')}
            value={templateId}
            onChange={applyTemplate}
            options={[
              { value: FREE_FORM, label: t('communications.noTemplate') },
              ...available.map((template) => ({
              value: template.id,
              label: template.name,
              hint: [
                t(`communicationChannels.${template.channel}`),
                t(`templateTypes.${template.templateType}`),
                template.language.toUpperCase(),
              ].join(' · '),
            })),
            ]}
            isLoading={templates.isPending}
            required
            description={t('communications.templateHint')}
          />

          <AsyncSelect
            label={t('communications.fields.recipientRole')}
            value={recipientRole}
            onChange={setRecipientRole}
            options={RECIPIENT_ROLES.map((role) => ({
              value: role,
              label: t(`recipientRoles.${role}`),
            }))}
            required
            description={t('communications.recipientHint')}
          />

          {chosen === undefined ? (
            <div className="grid gap-4 sm:grid-cols-2">
              <AsyncSelect
                label={t('communications.fields.channel')}
                value={channel}
                onChange={setChannel}
                options={COMMUNICATION_CHANNELS.map((value) => ({
                  value,
                  label: t(`communicationChannels.${value}`),
                }))}
                required
              />
              <AsyncSelect
                label={t('communications.fields.communicationType')}
                value={communicationType}
                onChange={setCommunicationType}
                // `invoice` est ecarte : c'est un document, pas un message.
                options={TEMPLATE_TYPES.filter((value) => value !== 'invoice').map((value) => ({
                  value,
                  label: t(`templateTypes.${value}`),
                }))}
                required
              />
            </div>
          ) : null}

          <>
              <CommunicationRecipientFields
                channel={effectiveChannel}
                value={recipient}
                onChange={(patch) => setRecipient((current) => ({ ...current, ...patch }))}
              />

              <CommunicationMessageFields
                channel={effectiveChannel}
                value={message}
                onChange={(patch) => setMessage((current) => ({ ...current, ...patch }))}
                showRenderHint={chosen !== undefined}
              />
          </>
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            onClick={() => void submit()}
            disabled={message.body.trim() === '' || create.isPending}
          >
            {t('communications.saveDraft')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
