import { act, renderHook } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { useOrderDraft } from './useOrderDraft'

describe('useOrderDraft', () => {
  it('démarre avec une ligne et un service, sans colis', () => {
    const { result } = renderHook(() => useOrderDraft())

    expect(result.current.draft.lines).toHaveLength(1)
    expect(result.current.draft.services).toHaveLength(1)
    expect(result.current.draft.packages).toEqual([])
  })

  /**
   * Le point central du §23 : retirer un élément ne doit décaler l'identité
   * d'aucun autre. Les clés survivent, seule leur position change.
   */
  it('conserve les clés des lignes restantes après une suppression', () => {
    const { result } = renderHook(() => useOrderDraft())

    act(() => result.current.addLine())
    act(() => result.current.addLine())

    const keys = result.current.draft.lines.map((line) => line.key)

    act(() => result.current.removeLine(keys[0]))

    expect(result.current.draft.lines.map((line) => line.key)).toEqual([keys[1], keys[2]])
  })

  it('retire l’affectation d’une ligne supprimée pour ne pas laisser de renvoi mort', () => {
    const { result } = renderHook(() => useOrderDraft())

    act(() => result.current.addPackage(null))

    const lineKey = result.current.draft.lines[0].key
    const packageKey = result.current.draft.packages[0].key

    act(() => result.current.assignLine(packageKey, lineKey, '2'))

    expect(result.current.draft.packages[0].lines).toHaveLength(1)

    act(() => result.current.removeLine(lineKey))

    expect(result.current.draft.packages[0].lines).toEqual([])
  })

  it('met à jour une affectation existante au lieu de la dupliquer', () => {
    const { result } = renderHook(() => useOrderDraft())

    act(() => result.current.addPackage(null))

    const lineKey = result.current.draft.lines[0].key
    const packageKey = result.current.draft.packages[0].key

    act(() => result.current.assignLine(packageKey, lineKey, '2'))
    act(() => result.current.assignLine(packageKey, lineKey, '5'))

    expect(result.current.draft.packages[0].lines).toEqual([{ lineKey, quantity: '5' }])
  })

  it('supprime un colis avec sa descendance et les renvois des services', () => {
    const { result } = renderHook(() => useOrderDraft())

    act(() => result.current.addPackage(null))

    const parentKey = result.current.draft.packages[0].key

    act(() => result.current.addPackage(parentKey))

    const childKey = result.current.draft.packages[1].key
    const serviceKey = result.current.draft.services[0].key

    act(() =>
      result.current.patchService(serviceKey, {
        packages: [{ packageKey: childKey, quantity: '1', handlingInstructions: '' }],
      }),
    )

    act(() => result.current.removePackage(parentKey))

    expect(result.current.draft.packages).toEqual([])
    expect(result.current.draft.services[0].packages).toEqual([])
  })

  /** Deux services ne peuvent pas partager une séquence : la contrainte existe en base. */
  it('attribue au nouveau service la séquence suivante', () => {
    const { result } = renderHook(() => useOrderDraft())

    act(() => result.current.addService())

    expect(result.current.draft.services.map((service) => service.sequence)).toEqual(['1', '2'])
  })

  it('donne une clé distincte à chaque élément ajouté', () => {
    const { result } = renderHook(() => useOrderDraft())

    act(() => result.current.addLine())
    act(() => result.current.addLine())

    const keys = new Set(result.current.draft.lines.map((line) => line.key))

    expect(keys.size).toBe(3)
  })
})
