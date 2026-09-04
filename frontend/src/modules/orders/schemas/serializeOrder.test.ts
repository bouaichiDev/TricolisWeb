import { describe, expect, it } from 'vitest'

import { emptyDraft, emptyLine, emptyPackage, emptyService } from './orderDraft'
import { serializeOrder, serializeOrderWithKeys } from './serializeOrder'

function draftWithLines(count: number) {
  const draft = emptyDraft()
  draft.customerId = '01JQZ000000000000000CUST'
  draft.agencyId = '01JQZ0000000000000000AGY1'
  draft.lines = Array.from({ length: count }, (_, index) => ({
    ...emptyLine(),
    name: `Ligne ${index + 1}`,
    quantity: String(index + 2),
  }))

  return draft
}

describe('serializeOrder', () => {
  /**
   * Le cœur du contrat : `lineKey` porte la **position** dans le tableau
   * envoyé, parce que `CreateOrderLines` n'indexe que par index. En mémoire,
   * l'identité reste une clé stable.
   */
  it('traduit la clé stable d’une ligne en position dans le tableau envoyé', () => {
    const draft = draftWithLines(3)
    const pkg = emptyPackage()
    pkg.lines = [{ lineKey: draft.lines[2].key, quantity: '1' }]
    draft.packages = [pkg]

    const payload = serializeOrder(draft)

    expect(payload.packages?.[0].lines?.[0].lineKey).toBe('2')
  })

  it('recalcule les positions après le retrait d’une ligne', () => {
    const draft = draftWithLines(3)
    const targetKey = draft.lines[2].key
    const pkg = emptyPackage()
    pkg.lines = [{ lineKey: targetKey, quantity: '1' }]
    draft.packages = [pkg]

    // La première ligne disparaît : la cible glisse de la position 2 à 1, sans
    // que sa clé change.
    draft.lines = draft.lines.slice(1)

    const payload = serializeOrder(draft)

    expect(payload.packages?.[0].lines?.[0].lineKey).toBe('1')
  })

  it('ignore une affectation dont la ligne n’existe plus', () => {
    const draft = draftWithLines(2)
    const pkg = emptyPackage()
    pkg.lines = [{ lineKey: 'ligne-supprimee', quantity: '1' }]
    draft.packages = [pkg]

    expect(serializeOrder(draft).packages?.[0].lines).toEqual([])
  })

  /** `CreateOrderPackages` construit son index au fil de la boucle. */
  it('place chaque colis parent avant ses enfants', () => {
    const draft = draftWithLines(1)
    const parent = emptyPackage()
    const child = { ...emptyPackage(), parentKey: parent.key }

    // Saisis dans l'ordre inverse : l'enfant d'abord.
    draft.packages = [child, parent]

    const keys = serializeOrder(draft).packages?.map((item) => item.key)

    expect(keys?.indexOf(parent.key)).toBeLessThan(keys?.indexOf(child.key) ?? -1)
  })

  it('rattache à la racine un colis dont le parent a disparu, sans le perdre', () => {
    const draft = draftWithLines(1)
    const orphan = { ...emptyPackage(), parentKey: 'parent-supprime' }
    draft.packages = [orphan]

    const packages = serializeOrder(draft).packages

    expect(packages).toHaveLength(1)
    expect(packages?.[0].parentKey).toBeNull()
  })

  /** Les colis sont `sometimes` côté serveur : une commande sans colis est valide. */
  it('accepte une commande sans aucun colis', () => {
    const payload = serializeOrder(draftWithLines(1))

    expect(payload.packages).toEqual([])
    expect(payload.lines).toHaveLength(1)
  })

  it('n’envoie pas de libellé imposé quand la ligne vient d’un article', () => {
    const draft = draftWithLines(1)
    draft.lines[0].catalogItemId = '01JQZ00000000000000ITEM1'
    draft.lines[0].name = ''

    expect(serializeOrder(draft).lines[0].name).toBeNull()
  })

  it('omet les mesures laissées vides plutôt que d’envoyer zéro', () => {
    const draft = draftWithLines(1)
    draft.lines[0].weight = ''

    expect(serializeOrder(draft).lines[0].weight).toBeUndefined()
  })

  it('rend les clés d’envoi dans l’ordre du tableau transmis', () => {
    const draft = draftWithLines(2)
    const parent = emptyPackage()
    const child = { ...emptyPackage(), parentKey: parent.key }
    draft.packages = [child, parent]
    draft.services = [emptyService(1)]

    const serialized = serializeOrderWithKeys(draft)

    expect(serialized.lineKeys).toEqual(draft.lines.map((line) => line.key))
    expect(serialized.packageKeys).toEqual([parent.key, child.key])
    expect(serialized.serviceKeys).toEqual([draft.services[0].key])
  })

  it('écarte du service un colis retiré entre-temps', () => {
    const draft = draftWithLines(1)
    draft.services[0].packages = [
      { packageKey: 'colis-supprime', quantity: '1', handlingInstructions: '' },
    ]

    expect(serializeOrder(draft).services[0].packages).toEqual([])
  })
})
