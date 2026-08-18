import { describe, expect, it } from 'vitest'

import { emptyContact, emptyDraft, emptyPackage, type OrderDraft } from './orderDraft'
import { validateDraft } from './validateDraft'

function complete(): OrderDraft {
  const draft = emptyDraft()

  draft.customerId = '01JQZ000000000000000CUST'
  draft.agencyId = '01JQZ0000000000000000AGY1'
  draft.lines[0].name = 'Palette de cartons'

  Object.assign(draft.services[0], {
    serviceId: '01JQZ00000000000000SERV1',
    addressId: '01JQZ00000000000000ADDR1',
    serviceNumber: 'SRV-1',
    requestedDate: '2026-09-01',
    unit: 'colis',
    customerUnitPrice: '10',
    customerTotalPrice: '10',
    providerUnitCost: '6',
    providerTotalCost: '6',
  })

  return draft
}

const paths = (draft: OrderDraft) => validateDraft(draft).issues.map((issue) => issue.path)

describe('validateDraft', () => {
  it('accepte un brouillon complet', () => {
    expect(validateDraft(complete()).issues).toEqual([])
  })

  it('exige client, agence et date', () => {
    const draft = emptyDraft()
    draft.lines[0].name = 'x'

    expect(paths(draft)).toContain('customerId')
    expect(paths(draft)).toContain('agencyId')
  })

  /** `lines` est `required|array|min:1` côté serveur. */
  it('refuse une commande sans ligne', () => {
    const draft = complete()
    draft.lines = []

    const report = validateDraft(draft)

    expect(report.stepsInError).toContain('lines')
    expect(report.issues[0].message).toBe('orders.wizard.requiredLines')
  })

  /** `services` est `required|array|min:1` côté serveur. */
  it('refuse une commande sans service', () => {
    const draft = complete()
    draft.services = []

    const report = validateDraft(draft)

    expect(report.stepsInError).toContain('services')
    expect(report.issues[0].message).toBe('orders.wizard.requiredServices')
  })

  /** Les colis sont facultatifs : rien ne doit être signalé. */
  it('accepte une commande sans colis', () => {
    const draft = complete()
    draft.packages = []

    expect(validateDraft(draft).stepsInError).not.toContain('packages')
  })

  /**
   * Les quatre montants sont `required` côté serveur, et le §29 interdit d'y
   * poser `0` en douce : ils doivent être saisis, donc signalés s'ils manquent.
   */
  it('exige les quatre montants d’un service', () => {
    const draft = complete()
    draft.services[0].customerUnitPrice = ''
    draft.services[0].providerTotalCost = ''

    const found = paths(draft)

    expect(found).toContain('services.0.customerUnitPrice')
    expect(found).toContain('services.0.providerTotalCost')
  })

  it('exige un libellé de ligne en l’absence d’article catalogue', () => {
    const draft = complete()
    draft.lines[0].name = ''

    expect(paths(draft)).toContain('lines.0.name')
  })

  it('n’exige pas de libellé quand la ligne vient d’un article', () => {
    const draft = complete()
    draft.lines[0].name = ''
    draft.lines[0].catalogItemId = '01JQZ00000000000000ITEM1'

    expect(paths(draft)).not.toContain('lines.0.name')
  })

  it('exige une quantité strictement positive sur une affectation de colis', () => {
    const draft = complete()
    const pkg = emptyPackage()
    pkg.lines = [{ lineKey: draft.lines[0].key, quantity: '0' }]
    draft.packages = [pkg]

    expect(paths(draft)).toContain('packages.0.lines.0.quantity')
  })

  it('exige un prénom pour un contact ponctuel, pas pour un contact enregistré', () => {
    const draft = complete()
    draft.services[0].contacts = [emptyContact()]

    expect(paths(draft)).toContain('services.0.contacts.0.firstName')

    draft.services[0].contacts[0].contactId = '01JQZ0000000000000CONT1'

    expect(paths(draft)).not.toContain('services.0.contacts.0.firstName')
  })

  it('rattache chaque anomalie à la clé stable de son élément', () => {
    const draft = complete()
    draft.lines[0].name = ''

    const issue = validateDraft(draft).issues.find((item) => item.path === 'lines.0.name')

    expect(issue?.entityKey).toBe(draft.lines[0].key)
  })
})
