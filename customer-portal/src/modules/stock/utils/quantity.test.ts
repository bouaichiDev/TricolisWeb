import { formatQuantity } from './quantity'

describe('formatQuantity', () => {
  it('formats a decimal string without going through a float', () => {
    expect(formatQuantity('1234567.500')).toBe('1 234 567,5')
    expect(formatQuantity('120.000')).toBe('120')
    expect(formatQuantity('0.125')).toBe('0,125')
    expect(formatQuantity('-3.100')).toBe('-3,1')
  })

  it('shows a dash when there is nothing', () => {
    expect(formatQuantity(null)).toBe('—')
  })
})
