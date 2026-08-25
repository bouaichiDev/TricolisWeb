import { useTranslation } from 'react-i18next'

import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/shared/components/ui/sheet'
import { formatDateTime } from '@/shared/utils/format'

import { CommunicationAttachmentList } from './CommunicationAttachmentList'
import { CommunicationStatusBadge } from './CommunicationStatusBadge'
import { useOrderCommunication } from '../hooks/useOrderCommunications'
import { abilitiesOf } from '../utils/communicationActions'
import type { OrderCommunication } from '../types/communication'

interface OrderCommunicationDetailDrawerProps {
  communication: OrderCommunication | null
  onClose: () => void
}

/** Les six horodatages du cycle de vie, dans l'ordre où ils surviennent. */
const TIMESTAMPS = [
  'scheduledAt',
  'queuedAt',
  'sentAt',
  'deliveredAt',
  'readAt',
  'failedAt',
] as const

/**
 * Détail d'une communication.
 *
 * Ce qui est montré est le **snapshot** : le sujet, le corps et le destinataire
 * tels qu'ils sont partis. Le template a pu changer depuis ; le reconstruire
 * afficherait un texte que personne n'a reçu.
 *
 * `providerResponse` n'est **jamais** affiché — la réponse brute d'un
 * fournisseur peut porter des identifiants techniques, et le §52 l'interdit.
 * `errorMessage`, lui, est rédigé pour être lu et l'est.
 */
export function OrderCommunicationDetailDrawer({
  communication,
  onClose,
}: OrderCommunicationDetailDrawerProps) {
  const { t } = useTranslation()
  const detail = useOrderCommunication(communication?.id ?? null)
  const shown = detail.data ?? communication

  const dates = shown
    ? TIMESTAMPS.filter((key) => shown[key] != null).map((key) => ({
        key,
        value: shown[key] as string,
      }))
    : []

  return (
    <Sheet open={communication !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
        <SheetHeader>
          <SheetTitle>{shown?.subject ?? shown?.template?.name ?? ''}</SheetTitle>
          <SheetDescription>
            {shown
              ? [
                  t(`communicationChannels.${shown.channel}`),
                  t(`recipientRoles.${shown.recipientRole}`),
                ].join(' · ')
              : ''}
          </SheetDescription>
        </SheetHeader>

        {shown ? (
          <div className="flex flex-col gap-4 px-4 pb-6">
            <div className="flex flex-wrap items-center gap-2">
              <CommunicationStatusBadge status={shown.status} />
              {shown.template ? (
                <span className="text-sm text-muted-foreground">{shown.template.name}</span>
              ) : null}
            </div>

            {shown.errorMessage ? (
              <Alert variant="destructive">
                <AlertDescription>{shown.errorMessage}</AlertDescription>
              </Alert>
            ) : null}

            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t('communications.fields.recipient')}
              </p>
              <p className="mt-1 text-sm">
                {[shown.recipientName, shown.recipientEmail, shown.recipientPhone]
                  .filter(Boolean)
                  .join(' · ') || t('communications.recipientResolved')}
              </p>
            </div>

            {dates.length > 0 ? (
              <dl className="grid grid-cols-2 gap-2 border-t pt-3">
                {dates.map((item) => (
                  <div key={item.key}>
                    <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                      {t(`communications.fields.${item.key}`)}
                    </dt>
                    <dd className="text-sm">{formatDateTime(item.value)}</dd>
                  </div>
                ))}
              </dl>
            ) : null}

            <div className="border-t pt-3">
              <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t('communications.fields.body')}
              </p>
              <p className="mt-1 whitespace-pre-wrap text-sm">{shown.body}</p>
            </div>

            <div className="border-t pt-3">
              <CommunicationAttachmentList
                communicationId={shown.id}
                editable={abilitiesOf(shown.status).edit}
              />
            </div>
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
