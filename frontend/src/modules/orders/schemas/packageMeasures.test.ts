import { describe, expect, it } from 'vitest'

import { emptyDraft, emptyLine, emptyPackage, emptyService } from './orderDraft'
import { packageMeasures, serviceContent } from './packageMeasures'

/** Deux lignes pesées, une palette contenant un carton. */
function palletWithCarton() {
  const draft = emptyDraft()
  const chairs = { ...emptyLine(), name: 'Chaise', quantity: '10', weight: '4', volume: '0.2' }
  const table = { ...emptyLine(), name: 'Table', quantity: '1', weight: '30', volume: '1.5' }
  const pallet = { ...emptyPackage(), lines: [{ lineKey: table.key, quantity: '1' }] }
  const carton = {
    ...emptyPackage(),
    parentKey: pallet.key,
    lines: [{ lineKey: chairs.key, quantity: '6' }],
  }

  draft.lines = [chairs, table]
  draft.packages = [pallet, carton]

  return { draft, pallet, carton }
}

describe('packageMeasures', () => {
  it('pèse un colis par ses lignes affectées : poids unitaire × quantité affectée', () => {
    const { draft, carton } = palletWithCarton()

    expect(packageMeasures(draft).get(carton.key)).toEqual({ weight: 24, volume: 1.2 })
  })

  it('ajoute au parent le contenu de ses enfants', () => {
    const { draft, pallet } = palletWithCarton()

    expect(packageMeasures(draft).get(pallet.key)).toEqual({ weight: 54, volume: 2.7 })
  })

  it('compte pour zéro une ligne sans poids ni volume', () => {
    const draft = emptyDraft()
    const pkg = { ...emptyPackage(), lines: [{ lineKey: draft.lines[0].key, quantity: '3' }] }
    draft.packages = [pkg]

    expect(packageMeasures(draft).get(pkg.key)).toEqual({ weight: 0, volume: 0 })
  })

  it('ne boucle pas sur une parenté circulaire', () => {
    const draft = emptyDraft()
    const a = emptyPackage()
    const b = { ...emptyPackage(), parentKey: a.key }
    a.parentKey = b.key
    draft.packages = [a, b]

    expect(packageMeasures(draft).size).toBe(2)
  })
})

describe('serviceContent', () => {
  it('additionne les colis cochés et les compte', () => {
    const { draft, carton } = palletWithCarton()
    const service = {
      ...emptyService(1),
      packages: [{ packageKey: carton.key, quantity: '', handlingInstructions: '' }],
    }

    expect(serviceContent(service, draft)).toEqual({ weight: 24, volume: 1.2, packageCount: 1 })
  })

  it('ne pèse pas deux fois un carton dont la palette est aussi cochée', () => {
    const { draft, pallet, carton } = palletWithCarton()
    const service = {
      ...emptyService(1),
      packages: [pallet, carton].map((item) => ({
        packageKey: item.key,
        quantity: '',
        handlingInstructions: '',
      })),
    }

    expect(serviceContent(service, draft)).toEqual({ weight: 54, volume: 2.7, packageCount: 2 })
  })

  it('ignore un colis retiré du brouillon', () => {
    const service = {
      ...emptyService(1),
      packages: [{ packageKey: 'disparu', quantity: '', handlingInstructions: '' }],
    }

    expect(serviceContent(service, emptyDraft())).toEqual({ weight: 0, volume: 0, packageCount: 0 })
  })
})
