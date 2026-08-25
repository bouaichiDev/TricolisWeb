import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

import { CommunicationRecipientFields } from './CommunicationRecipientFields'
import { useCommunicationTemplateList } from '../hooks/useCommunicationTemplates'
import { useCreateOrderCommunication } from '../hooks/useOrderCommunications'
import { hasSubject, RECIPIENT_ROLES } from '../types/communication'

interface CreateOrderCommunicationDialogProps {
  orderId: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

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

  const [templateId, setTemplateId] = useState('')
  const [recipientRole, setRecipientRole] = useState('customer')
  const [recipient, setRecipient] = useState({ name: '', email: '', phone: '' })
  const [subject, setSubject] = useState('')
  const [body, setBody] = useState('')
  const [scheduledAt, setScheduledAt] = useState('')
  const [error, setError] = useState<string | null>(null)

  // Seuls les templates actifs : proposer un template retire ferait partir un
  // message que l'organisation a justement decide de ne plus utiliser.
  const templates = useCommunicationTemplateList(
    { page: 1, perPage: 100, isActive: true, sort: 'name', direction: 'asc' },
    open,
  )

  const available = templates.data?.data ?? []
  const chosen = available.find((item) => item.id === templateId)

  const applyTemplate = (id: string) => {
    setTemplateId(id)

    const template = available.find((item) => item.id === id)
    if (template === undefined) return

    setSubject(template.subjectTemplate ?? '')
    setBody(template.bodyTemplate)
  }

  const submit = async () => {
    if (chosen === undefined) return
    setError(null)

    try {
      await create.mutateAsync({
        templateId: chosen.id,
        channel: chosen.channel,
        communicationType: chosen.templateType,
        recipientRole,
        recipientName: blank(recipient.name),
        recipientEmail: blank(recipient.email),
        recipientPhone: blank(recipient.phone),
        subject: hasSubject(chosen.channel) ? blank(subject) : null,
        body: blank(body),
        scheduledAt: scheduledAt === '' ? null : new Date(scheduledAt).toISOString(),
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
            options={available.map((template) => ({
              value: template.id,
              label: template.name,
              hint: [
                t(`communicationChannels.${template.channel}`),
                t(`communicationTemplateTypes.${template.templateType}`),
                template.language.toUpperCase(),
              ].join(' · '),
            }))}
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

          {chosen ? (
            <>
              <CommunicationRecipientFields
                channel={chosen.channel}
                value={recipient}
                onChange={(patch) => setRecipient((current) => ({ ...current, ...patch }))}
              />

              <Alert>
                <AlertDescription>{t('communications.noRenderHint')}</AlertDescription>
              </Alert>

              {hasSubject(chosen.channel) ? (
                <ControlledField
                  label={t('communications.fields.subject')}
                  value={subject}
                  onChange={setSubject}
                />
              ) : null}

              <ControlledField
                label={t('communications.fields.body')}
                value={body}
                onChange={setBody}
                multiline
              />

              <ControlledField
                label={t('communications.fields.scheduledAt')}
                type="datetime-local"
                value={scheduledAt}
                onChange={setScheduledAt}
                description={t('communications.scheduleHint')}
              />
            </>
          ) : null}
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            onClick={() => void submit()}
            disabled={chosen === undefined || create.isPending}
          >
            {t('communications.saveDraft')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
