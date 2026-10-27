import { statusAppearance } from './statusTones'

describe('statusAppearance', () => {
  it('uses the color set in the status referential', () => {
    expect(statusAppearance('planned', '#9333EA')).toEqual({
      className: '',
      style: { color: '#9333EA', backgroundColor: '#9333EA1f', borderColor: '#9333EA55' },
    })
  })

  it('falls back to the tone of the code, then to the neutral tone', () => {
    expect(statusAppearance('cancelled', null).className).toContain('text-destructive')
    expect(statusAppearance('unknown').className).toContain('text-muted-foreground')
  })

  it('ignores a value that is not #RRGGBB', () => {
    expect(statusAppearance('planned', 'red; background:url(x)').style).toBeUndefined()
  })
})
