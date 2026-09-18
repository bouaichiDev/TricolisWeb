import { describe, expect, it } from 'vitest'

import type { OrderServiceContact } from '../types/orderDetail'
import { emptyContact } from './orderDraft'
import { contactChanges, contactDrafts, contactIssues } from './serviceContactChanges'

function saved(id: string, firstName: string): OrderServiceContact {
  return {
    id,
    orderServiceId: 'SRV',
    contactId: null,
    contactRole: 'delivery',
    firstName,
    lastName: null,
    phone: null,
    mobile: '+41 79 000 00 00',
    email: null,
    isPrimary: true,
    createdAt: '2026-09-01T00:00:00Z',
  }
}

describe('contactChanges', () => {
  it('ne renvoie rien pour des contacts inchangés', () => {
    const contacts = [saved('C1', 'Anne')]

    expect(contactChanges(contacts, contactDrafts(contacts))).toEqual({
      removed: [],
      updated: [],
      created: [],
    })
  })

  it('distingue un contact modifié, ajouté et retiré', () => {
    const contacts = [saved('C1', 'Anne'), saved('C2', 'Marc')]
    const [anne] = contactDrafts(contacts)
    const added = { ...emptyContact(), firstName: 'Léa' }
    const drafts = [{ ...anne, lastName: 'Rochat' }, added]

    const changes = contactChanges(contacts, drafts)

    expect(changes.removed).toEqual(['C2'])
    expect(changes.updated).toEqual([{ index: 0, draft: drafts[0] }])
    expect(changes.created).toEqual([{ index: 1, draft: added }])
  })
})

describe('contactIssues', () => {
  it('exige un prénom pour un contact ponctuel, pas pour un contact enregistré', () => {
    const drafts = [emptyContact(), { ...emptyContact(), contactId: '01JQZ0000000000000CONT1' }]

    expect(contactIssues('service', drafts).map((issue) => issue.path)).toEqual([
      'contacts.0.firstName',
    ])
  })
})
