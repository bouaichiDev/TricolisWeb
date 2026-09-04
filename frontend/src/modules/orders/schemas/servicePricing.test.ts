import { describe, expect, it } from 'vitest'

import { emptyService } from './orderDraft'
import { withDerivedTotals } from './servicePricing'

const service = () => ({ ...emptyService(1), quantity: '3' })

describe('withDerivedTotals', () => {
  it('calcule le total client depuis le prix unitaire et la quantité', () => {
    const patch = withDerivedTotals(service(), { customerUnitPrice: '10' })

    expect(patch).toEqual({ customerUnitPrice: '10', customerTotalPrice: '30' })
  })

  it('calcule le coût total fournisseur de la même façon', () => {
    const patch = withDerivedTotals(service(), { providerUnitCost: '4.5' })

    expect(patch).toEqual({ providerUnitCost: '4.5', providerTotalCost: '13.5' })
  })

  it('recalcule les deux totaux quand la quantité change', () => {
    const current = { ...service(), customerUnitPrice: '10', providerUnitCost: '6' }
    const patch = withDerivedTotals(current, { quantity: '5' })

    expect(patch.customerTotalPrice).toBe('50')
    expect(patch.providerTotalCost).toBe('30')
  })

  /**
   * Une remise ou un forfait s'écrivent à la main : les écraser à la frappe
   * suivante ferait perdre la valeur voulue.
   */
  it('respecte un total saisi à la main', () => {
    const current = { ...service(), customerUnitPrice: '10' }
    const patch = withDerivedTotals(current, { customerTotalPrice: '25' })

    expect(patch).toEqual({ customerTotalPrice: '25' })
  })

  it('ne calcule rien tant que le prix unitaire est vide', () => {
    const patch = withDerivedTotals(service(), { quantity: '4' })

    expect(patch).toEqual({ quantity: '4' })
  })

  it('laisse les autres champs intacts', () => {
    const patch = withDerivedTotals(service(), { serviceNumber: 'SRV-9' })

    expect(patch).toEqual({ serviceNumber: 'SRV-9' })
  })

  it('arrondit au centime', () => {
    const current = { ...service(), quantity: '3' }
    const patch = withDerivedTotals(current, { customerUnitPrice: '10.005' })

    expect(patch.customerTotalPrice).toBe('30.02')
  })
})
