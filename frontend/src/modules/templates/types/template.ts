import type { CommunicationChannel } from '@/modules/communications/types/communication'

/**
 * Nature métier d'un modèle — **valeurs exactes du backend**.
 *
 * En `snake_case` parce que les cas PHP le sont : envoyer `INVOICE` ferait
 * échouer `Rule::in(TemplateType::values())`.
 *
 * `invoice` est entré en Phase 9. Il désigne un **document**, pas un message :
 * c'est la seule valeur qui interdit un canal.
 */
export const TEMPLATE_TYPES = [
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
  'invoice',
  'custom',
] as const

export type TemplateType = (typeof TEMPLATE_TYPES)[number]

/** Format du corps : `html` pour un e-mail mis en forme, `text` sinon. */
export const BODY_FORMATS = ['text', 'html'] as const

export type BodyFormat = (typeof BODY_FORMATS)[number]

/** Portée d'un modèle, telle que le serveur la calcule. */
export type TemplateScope = 'global' | 'customer'

/**
 * Un modèle de facture est un document : ni canal, ni objet, ni destinataire.
 *
 * Le serveur refuse les deux combinaisons contraires ; l'écran les évite pour
 * ne pas faire saisir ce qui sera rejeté.
 */
export function isDocumentType(templateType: string): boolean {
  return templateType === 'invoice'
}

/**
 * Modèle — `TemplateResource`.
 *
 * `customerId` porte la personnalisation : nul, le modèle vaut pour toute
 * l'organisation ; renseigné, il ne vaut que pour ce client. Le serveur choisit
 * entre les deux, et ne sert jamais celui d'un tiers.
 */
export interface Template {
  id: string
  organizationId: string
  customerId: string | null
  customerName?: string | null
  serviceId: string | null
  serviceName?: string | null
  scope: TemplateScope
  code: string
  name: string
  channel: CommunicationChannel | null
  templateType: TemplateType
  subjectTemplate: string | null
  bodyTemplate: string
  bodyFormat: BodyFormat
  language: string
  availableVariables: string[] | null
  isDefault: boolean
  isActive: boolean
  rulesCount?: number
  communicationsCount?: number
  invoicesCount?: number
  createdAt: string
  updatedAt: string
}

/**
 * Le modèle ne peut plus être supprimé : il fait partie de l'historique.
 *
 * Le serveur refuse en 409 ; le savoir avant permet de désactiver le bouton
 * plutôt que de laisser l'utilisateur découvrir le refus après coup.
 */
export function isTemplateInUse(template: Template): boolean {
  return (
    (template.rulesCount ?? 0) > 0 ||
    (template.communicationsCount ?? 0) > 0 ||
    (template.invoicesCount ?? 0) > 0
  )
}
