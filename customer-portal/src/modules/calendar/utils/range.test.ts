import { toDateOnly } from './range'

describe('toDateOnly', () => {
  it('keeps the local day instead of converting to UTC', () => {
    expect(toDateOnly(new Date(2026, 8, 1, 0, 0))).toBe('2026-09-01')
    expect(toDateOnly(new Date(2026, 11, 31, 23, 59))).toBe('2026-12-31')
  })
})
