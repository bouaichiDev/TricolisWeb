import { describe, expect, it } from 'vitest'

import { lineAllocations } from './allocations'
import { emptyDraft, emptyLine, emptyPackage } from './orderDraft'
import { descendantKeys, packageTree } from './packageOrder'

function draftWithOneLine(quantity: string) {
  const draft = emptyDraft()
  draft.lines = [{ ...emptyLine(), quantity }]

  return draft
}

describe('lineAllocations', () => {
  it('additionne les affectations d’une ligne réparties entre plusieurs colis', () => {
    const draft = draftWithOneLine('10')
    const lineKey = draft.lines[0].key

    draft.packages = [
      { ...emptyPackage(), lines: [{ lineKey, quantity: '4' }] },
      { ...emptyPackage(), lines: [{ lineKey, quantity: '3' }] },
    ]

    const allocation = lineAllocations(draft).get(lineKey)

    expect(allocation).toEqual({ ordered: 10, assigned: 7, remaining: 3, over: false })
  })

  /** `PackageLineAllocator` refuse le dépassement : l'écran le montre avant l'envoi. */
  it('signale un dépassement de la quantité commandée', () => {
    const draft = draftWithOneLine('5')
    const lineKey = draft.lines[0].key

    draft.packages = [{ ...emptyPackage(), lines: [{ lineKey, quantity: '6' }] }]

    expect(lineAllocations(draft).get(lineKey)?.over).toBe(true)
  })

  it('compte zéro affecté pour une ligne qu’aucun colis ne reprend', () => {
    const draft = draftWithOneLine('2')

    expect(lineAllocations(draft).get(draft.lines[0].key)?.remaining).toBe(2)
  })
})

describe('packageTree', () => {
  it('classe les enfants sous leur parent avec la bonne profondeur', () => {
    const parent = emptyPackage()
    const child = { ...emptyPackage(), parentKey: parent.key }
    const grandchild = { ...emptyPackage(), parentKey: child.key }

    const nodes = packageTree([grandchild, child, parent])

    expect(nodes.map((node) => node.draft.key)).toEqual([parent.key, child.key, grandchild.key])
    expect(nodes.map((node) => node.depth)).toEqual([0, 1, 2])
  })

  it('remonte toute la descendance d’un colis', () => {
    const parent = emptyPackage()
    const child = { ...emptyPackage(), parentKey: parent.key }
    const grandchild = { ...emptyPackage(), parentKey: child.key }

    expect(descendantKeys([parent, child, grandchild], parent.key)).toEqual([
      child.key,
      grandchild.key,
    ])
  })
})
