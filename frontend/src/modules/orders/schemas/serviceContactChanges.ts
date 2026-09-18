import type { OrderServiceContact } from '../types/orderDetail'
import type { OrderServiceContactPayload } from '../types/orderPayload'
import type { ServiceContactDraft } from './orderDraft'
import type { OrderIssue } from './orderErrors'

const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

/**
 * Contacts enregistrés d'un service, en brouillons modifiables.
 *
 * La clé d'un brouillon est l'identifiant du contact : c'est elle qui permet
 * ensuite de distinguer un contact modifié d'un contact ajouté.
 */
export function contactDrafts(contacts: OrderServiceContact[]): ServiceContactDraft[] {
  return contacts.map((contact) => ({
    key: contact.id,
    contactId: contact.contactId,
    contactRole: contact.contactRole ?? 'other',
    isPrimary: contact.isPrimary,
    firstName: contact.firstName ?? '',
    lastName: contact.lastName ?? '',
    phone: contact.phone ?? '',
    mobile: contact.mobile ?? '',
    email: contact.email ?? '',
  }))
}

/** Charge utile de `StoreOrderServiceContactRequest`, la même qu'à la création. */
export function contactPayload(draft: ServiceContactDraft): OrderServiceContactPayload {
  return {
    contactId: draft.contactId,
    contactRole: draft.contactRole,
    isPrimary: draft.isPrimary,
    firstName: blank(draft.firstName),
    lastName: blank(draft.lastName),
    phone: blank(draft.phone),
    mobile: blank(draft.mobile),
    email: blank(draft.email),
  }
}

export interface ContactChanges {
  removed: string[]
  /** Brouillons à renvoyer, avec leur position pour y ramener une erreur. */
  updated: { index: number; draft: ServiceContactDraft }[]
  created: { index: number; draft: ServiceContactDraft }[]
}

/**
 * Ce qu'il faut envoyer pour passer des contacts enregistrés aux brouillons.
 *
 * Un contact inchangé n'est pas renvoyé : chaque écriture laisse une trace
 * d'audit, et une trace sans changement n'apprend rien à personne.
 */
export function contactChanges(
  saved: OrderServiceContact[],
  drafts: ServiceContactDraft[],
): ContactChanges {
  const byId = new Map(contactDrafts(saved).map((draft) => [draft.key, draft]))
  const kept = new Set(drafts.map((draft) => draft.key))
  const changes: ContactChanges = {
    removed: saved.filter((contact) => !kept.has(contact.id)).map((contact) => contact.id),
    updated: [],
    created: [],
  }

  drafts.forEach((draft, index) => {
    const before = byId.get(draft.key)

    if (before === undefined) changes.created.push({ index, draft })
    else if (JSON.stringify(contactPayload(before)) !== JSON.stringify(contactPayload(draft))) {
      changes.updated.push({ index, draft })
    }
  })

  return changes
}

/**
 * Contrôle avant envoi, calqué sur `StoreOrderServiceContactRequest` : sans
 * contact enregistré, le prénom est requis.
 */
export function contactIssues(serviceKey: string, drafts: ServiceContactDraft[]): OrderIssue[] {
  return drafts.flatMap((draft, index) =>
    draft.contactId === null && draft.firstName.trim() === ''
      ? [contactIssue(serviceKey, index, 'firstName', 'validation.required')]
      : [],
  )
}

export function contactIssue(
  serviceKey: string,
  index: number,
  field: string,
  message: string,
): OrderIssue {
  return {
    path: `contacts.${index}.${field}`,
    message,
    step: 'services',
    entityKey: serviceKey,
    sub: { kind: 'contacts', index },
    field,
  }
}
