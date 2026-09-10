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
  // Un document, comme la facture : il ne part par aucun canal, il se rend et
  // se remet avec la marchandise.
  'delivery_note',
  // S'adresse a un membre de l'organisation, non a un client : le seul de cette
  // liste qui ne parle pas d'une commande. Il y figure parce qu'il part au nom
  // du transporteur, et qu'un texte signe « Laravel » ne rassure personne.
  'password_reset',
  'custom',
] as const

export type TemplateType = (typeof TEMPLATE_TYPES)[number]

/** Format du corps : `html` pour un e-mail mis en forme, `text` sinon. */
export const BODY_FORMATS = ['text', 'html'] as const

export type BodyFormat = (typeof BODY_FORMATS)[number]

/** Portée d'un modèle, telle que le serveur la calcule. */
export type TemplateScope = 'global' | 'customer'

/**
 * Les natures qui décrivent une **mise en page**, non un message.
 *
 * Recopiées de `TemplateType::documents()`. Une seule liste, ici comme sur le
 * serveur : sans elle, ajouter un troisième document obligeait à retrouver
 * chaque `=== 'invoice'` disséminé dans les écrans.
 */
export const DOCUMENT_TYPES = ['invoice', 'delivery_note'] as const

/**
 * Un document n'a ni canal, ni objet, ni destinataire.
 *
 * Le serveur refuse les deux combinaisons contraires ; l'écran les évite pour
 * ne pas faire saisir ce qui sera rejeté.
 */
export function isDocumentType(templateType: string): boolean {
  return (DOCUMENT_TYPES as readonly string[]).includes(templateType)
}

/**
 * Le rayon dans lequel un modèle se range — `TemplateCategory` du serveur.
 *
 * Un comptable qui cherche sa mise en page de facture n'a pas à la trouver au
 * milieu des SMS de rendez-vous, ni un exploitant son BL au milieu des
 * courriels. Le menu ouvre la page sur l'un des trois ; le filtre permet d'en
 * changer sans repasser par le menu.
 */
export const TEMPLATE_CATEGORIES = ['communication', 'invoice', 'delivery_note'] as const

export type TemplateCategory = (typeof TEMPLATE_CATEGORIES)[number]

/** La catégorie d'une nature : « communication » est le cas général. */
export function categoryOf(templateType: string): TemplateCategory {
  if (templateType === 'invoice') return 'invoice'
  if (templateType === 'delivery_note') return 'delivery_note'

  return 'communication'
}

/** Les natures proposées dans un rayon donné. */
export function typesInCategory(category: TemplateCategory | undefined): readonly TemplateType[] {
  if (category === undefined) return TEMPLATE_TYPES

  return TEMPLATE_TYPES.filter((type) => categoryOf(type) === category)
}

/** La nature qu'une catégorie impose d'elle-même, quand elle n'en a qu'une. */
export function soleTypeOf(category: TemplateCategory | undefined): TemplateType | undefined {
  const types = typesInCategory(category)

  return types.length === 1 ? types[0] : undefined
}

/**
 * Le rayon d'arrivée, lu dans l'URL.
 *
 * `templateType` y est encore honoré pour une seule raison : c'était l'adresse
 * du menu de facturation avant l'arrivée des catégories, et un signet posé
 * dessus doit continuer d'ouvrir les modèles de facture, pas la liste complète.
 */
export function categoryFromParams(
  category: string | null,
  templateType: string | null,
): TemplateCategory | undefined {
  if (category !== null && (TEMPLATE_CATEGORIES as readonly string[]).includes(category)) {
    return category as TemplateCategory
  }

  return templateType === null ? undefined : categoryOf(templateType)
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
