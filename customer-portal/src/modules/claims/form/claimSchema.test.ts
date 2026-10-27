import { claimSchema, toClaimPayload } from './claimSchema'

const valid = { orderId: '01ORDER', orderServiceId: '01SVC', claimType: 'damage', title: 'Carton ouvert', description: '' }

describe('claimSchema', () => {
  it('accepts a claim with or without an order', () => {
    expect(claimSchema.safeParse(valid).success).toBe(true)
    expect(claimSchema.safeParse({ ...valid, orderId: '', orderServiceId: '' }).success).toBe(true)
  })

  it('requires a title, a type, and the order of a service', () => {
    const result = claimSchema.safeParse({ ...valid, orderId: '', title: ' ', claimType: '' })

    expect(result.success).toBe(false)
    expect(result.error?.issues.map((issue) => issue.path[0])).toEqual(expect.arrayContaining(['title', 'claimType', 'orderId']))
  })

  it('sends nulls rather than empty strings', () => {
    expect(toClaimPayload({ ...valid, orderServiceId: '', description: '' })).toEqual({
      orderId: '01ORDER', orderServiceId: null, claimType: 'damage', title: 'Carton ouvert', description: null,
    })
  })
})
