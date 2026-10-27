import { emptyOrder, emptyPackage, fromDetail, toPayload } from './orderMapping'
import type { OrderDetail } from '../types'

describe('order form mapping', () => {
  it('turns local line uids into the line keys the API expects', () => {
    const values = emptyOrder()
    values.lines = [
      { ...values.lines[0], uid: 'a', catalogItemId: 'ITEM1', quantity: '2' },
      { ...values.lines[0], uid: 'b', catalogItemId: 'ITEM2', quantity: '1' },
    ]
    values.packages = [{ ...emptyPackage(), uid: 'P1', lines: [{ lineUid: 'b', quantity: '1' }] }]
    values.services[0] = { ...values.services[0], serviceId: 'S', addressId: 'A', requestedDate: '2026-10-10', packageUids: ['P1'] }

    const payload = toPayload(values)

    expect(payload.lines).toHaveLength(2)
    expect(payload.packages[0]).toMatchObject({ key: 'P1', lines: [{ lineKey: '1', quantity: 1 }] })
    expect(payload.services[0]).toMatchObject({ addressId: 'A', address: null, packages: [{ packageKey: 'P1' }] })
  })

  it('sends a one-off recipient instead of an address id', () => {
    const values = emptyOrder()
    values.services[0] = {
      ...values.services[0],
      addressMode: 'new',
      addressId: 'IGNORED',
      recipient: { firstName: 'Lea', lastName: 'Muller', company: '', addressLine1: '5 Rue', postalCode: '1003', city: 'Lausanne', country: 'ch', phone: '' },
    }

    const service = toPayload(values).services[0]

    expect(service.addressId).toBeNull()
    expect(service.address).toMatchObject({ city: 'Lausanne', country: 'CH' })
  })

  it('never sends the customer, the status or internal fields', () => {
    expect(Object.keys(toPayload(emptyOrder()))).toEqual(['customerReference', 'externalReference', 'workerRemark', 'lines', 'packages', 'services'])
  })

  it('reloads an order detail into the form', () => {
    const detail = {
      customerReference: 'IKEA-1',
      externalReference: null,
      workerRemark: null,
      lines: [{ id: 'L1', catalogItemId: 'I1', articleCode: 'BILLY', name: 'Billy', description: null, quantity: '2.000', weight: '30.5', volume: null, deliveredQuantity: null }],
      packages: [{ id: 'P1', parentPackageId: null, packageType: null, groupingType: null, barcode: null, reference: 'R', description: null, quantity: null, weight: null, volume: null, status: { code: 'draft', label: 'Brouillon', icon: null }, lines: [{ orderLineId: 'L1', quantity: '2' }] }],
      services: [{ id: 'S1', serviceNumber: 'S1', sequence: 1, service: { id: 'SV', code: 'DELIVERY', name: 'Livraison' }, requestedDate: '2026-10-10', requestedFrom: '08:00', requestedTo: '12:00', instructions: null, status: { code: 'draft', label: 'Brouillon', icon: null }, address: { id: 'AD', name: null, addressLine1: null, addressLine2: null, postalCode: null, city: null, country: null, instructions: null }, contacts: [], packages: [{ packageId: 'P1', quantity: '1' }] }],
    } as unknown as OrderDetail

    const values = fromDetail(detail)

    expect(values.lines[0]).toMatchObject({ uid: 'L1', catalogItemId: 'I1', quantity: '2' })
    expect(values.packages[0].lines[0].lineUid).toBe('L1')
    expect(values.services[0]).toMatchObject({ addressMode: 'book', addressId: 'AD', requestedFrom: '08:00', packageUids: ['P1'] })
  })
})
