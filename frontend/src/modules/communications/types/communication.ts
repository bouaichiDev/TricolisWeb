import type { Document } from '@/modules/documents/types/document'
import type { Template, TemplateType } from '@/modules/templates/types/template'
import type { CompactUser } from '@/modules/tracking/types/trackingEvent'

/**
 * Les énumérations du domaine, **valeurs exactes du backend**.
 *
 * Elles sont en `snake_case` parce que les cas PHP le sont : envoyer `EMAIL`
 * ferait échouer `Rule::in(CommunicationChannel::values())`.
 *
 * `TemplateType` vit dans le module `templates` depuis la Phase 9 : la même
 * énumération sert aux messages et aux documents, et la dupliquer ici aurait
 * laissé les deux copies diverger.
 */
export const COMMUNICATION_CHANNELS = [
  'email',
  'sms',
  'whatsapp',
  'push_notification',
  'internal_notification',
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

/**
 * Les onze événements métier qui peuvent déclencher une règle.
 *
 * Aucun n'est émis par les Phases 1 à 8 : le backend Phase 9 les déclare, et
 * leur branchement attend que les événements existent. Une règle se configure
 * donc dès aujourd'hui, et s'appliquera le jour où l'événement sera émis.
 */
export const COMMUNICATION_EVENT_TYPES = [
  'order_created',
  'order_confirmed',
  'order_cancelled',
  'service_planned',
  'appointment_requested',
  'appointment_confirmed',
  'driver_assigned',
  'tour_stop_approaching',
  'service_completed',
  'pod_created',
  'claim_created',
] as const

export type CommunicationChannel = (typeof COMMUNICATION_CHANNELS)[number]
export type CommunicationStatus = (typeof COMMUNICATION_STATUSES)[number]
export type CommunicationEventType = (typeof COMMUNICATION_EVENT_TYPES)[number]
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
 * Une communication n'est plus modifiable une fois partie.
 *
 * Le §83 l'exige : les instantanés restent historiques. Le serveur refuse ;
 * l'écran cache le bouton plutôt que de laisser découvrir le refus.
 */
export function isCommunicationSent(status: string): boolean {
  return status === 'sent' || status === 'delivered' || status === 'read'
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
 * `templateVariables` conservent ce qui est parti, même si le modèle change
 * ensuite. Reconstruire un ancien message depuis le modèle actuel montrerait
 * un texte que personne n'a jamais reçu.
 *
 * `communicationRuleId` dit d'où vient le message : renseigné, une règle l'a
 * produit ; nul avec un `createdBy`, quelqu'un l'a écrit. Aucun champ `origin`
 * n'existe, et le §75 interdit d'en inventer un.
 *
 * `providerResponse` est exposé par la ressource mais **jamais affiché** : la
 * réponse brute d'un fournisseur peut porter des identifiants techniques.
 * `errorMessage`, lui, est rédigé pour être lu.
 */
export interface OrderCommunication {
  id: string
  organizationId: string
  orderId: string
  orderNumber?: string | null
  templateId: string | null
  communicationRuleId: string | null
  channel: CommunicationChannel
  communicationType: TemplateType
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
  template?: Template
  attachments?: CommunicationAttachment[]
  attachmentsCount?: number
  createdAt: string
  updatedAt: string
}
