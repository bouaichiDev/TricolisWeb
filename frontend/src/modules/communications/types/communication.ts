import type { Document } from '@/modules/documents/types/document'
import type { CompactUser } from '@/modules/tracking/types/trackingEvent'

/**
 * Les quatre énumérations du domaine, **valeurs exactes du backend**.
 *
 * Elles sont en `snake_case` parce que les cas PHP le sont : envoyer `EMAIL`
 * ferait échouer `Rule::in(CommunicationChannel::values())`.
 */
export const COMMUNICATION_CHANNELS = [
  'email',
  'sms',
  'whatsapp',
  'push_notification',
  'internal_notification',
] as const

export const COMMUNICATION_TEMPLATE_TYPES = [
  'appointment_request',
  'appointment_confirmation',
  'appointment_reminder',
  'driver_assigned',
  'driver_departed',
  'arrival_estimate',
  'arrival_soon',
  'delivery_confirmation',
  'delivery_failed',
  'pod_available',
  'order_cancelled',
  'custom',
] as const

export const COMMUNICATION_STATUSES = [
  'draft',
  'scheduled',
  'queued',
  'sending',
  'sent',
  'delivered',
  'read',
  'failed',
  'cancelled',
] as const

export const RECIPIENT_ROLES = [
  'customer',
  'load_contact',
  'delivery_contact',
  'billing_contact',
  'internal_user',
  'custom',
] as const

/** Format du corps : `html` pour un e-mail mis en forme, `text` sinon. */
export const BODY_FORMATS = ['text', 'html'] as const

export type BodyFormat = (typeof BODY_FORMATS)[number]
export type CommunicationChannel = (typeof COMMUNICATION_CHANNELS)[number]
export type CommunicationTemplateType = (typeof COMMUNICATION_TEMPLATE_TYPES)[number]
export type CommunicationStatus = (typeof COMMUNICATION_STATUSES)[number]
export type RecipientRole = (typeof RECIPIENT_ROLES)[number]

/** Canaux où le sujet a un sens. Un SMS n'en a pas. */
export function hasSubject(channel: string): boolean {
  return channel === 'email' || channel === 'internal_notification'
}

/** Canaux qui écrivent à un numéro plutôt qu'à une adresse. */
export function usesPhone(channel: string): boolean {
  return channel === 'sms' || channel === 'whatsapp'
}

/**
 * Modèle de message — `CommunicationTemplateResource`.
 *
 * `serviceId` est facultatif : un template peut valoir pour toute
 * l'organisation ou pour un service précis.
 *
 * `rulesCount` existe et n'est **pas** exploité : les règles de communication
 * sont hors périmètre de cette phase.
 */
export interface CommunicationTemplate {
  id: string
  organizationId: string
  serviceId: string | null
  code: string
  name: string
  channel: CommunicationChannel
  templateType: CommunicationTemplateType
  subjectTemplate: string | null
  bodyTemplate: string
  bodyFormat: BodyFormat
  language: string
  availableVariables: string[] | null
  isDefault: boolean
  isActive: boolean
  rulesCount?: number
  communicationsCount?: number
  createdAt: string
  updatedAt: string
}

/**
 * Pièce jointe — `CommunicationAttachmentResource`.
 *
 * Les *snapshots* de nom et de type figent ce qui a été envoyé : renommer le
 * document ensuite ne réécrit pas l'historique.
 */
export interface CommunicationAttachment {
  id: string
  communicationId: string
  documentId: string
  fileNameSnapshot: string
  mimeTypeSnapshot: string
  document?: Document
  createdAt: string
}

/**
 * Communication d'une commande — `OrderCommunicationResource`.
 *
 * C'est un **instantané** : `recipientName`, `subject`, `body` et
 * `templateVariables` conservent ce qui est parti, même si le template change
 * ensuite. Reconstruire un ancien message depuis le template actuel montrerait
 * un texte que personne n'a jamais reçu.
 *
 * `communicationRuleId` reste au contrat, en **lecture seule** : une
 * communication manuelle l'envoie à `null`, et les règles sont hors périmètre.
 *
 * `providerResponse` est exposé par la ressource mais **jamais affiché** : la
 * réponse brute d'un fournisseur peut porter des identifiants techniques.
 * `errorMessage`, lui, est rédigé pour être lu.
 */
export interface OrderCommunication {
  id: string
  organizationId: string
  orderId: string
  templateId: string | null
  communicationRuleId: string | null
  channel: CommunicationChannel
  communicationType: CommunicationTemplateType
  recipientRole: RecipientRole
  recipientName: string | null
  recipientEmail: string | null
  recipientPhone: string | null
  subject: string | null
  body: string | null
  templateVariables?: Record<string, unknown> | null
  status: CommunicationStatus
  scheduledAt: string | null
  queuedAt?: string | null
  sentAt: string | null
  deliveredAt?: string | null
  readAt?: string | null
  failedAt?: string | null
  providerMessageId?: string | null
  errorMessage?: string | null
  createdBy: string | null
  creator?: CompactUser
  template?: CommunicationTemplate
  attachments?: CommunicationAttachment[]
  attachmentsCount?: number
  createdAt: string
  updatedAt: string
}
