import { describe, expect, it } from 'vitest'

import { formatStockQuantity, sumQuantities } from './stockSources'
import { isReleased, movementDirection } from '../types/stock'

describe('quantités de stock', () => {
  /**
   * L'API rend des `decimal(12,3)` **en chaînes** : `"100.500"` relu en
   * flottant se réaffiche `100.49999999999999`. La conversion n'a lieu qu'à
   * l'affichage, et la valeur d'origine n'est jamais réécrite.
   */
  it('lit une quantité décimale rendue en chaîne', () => {
    expect(formatStockQuantity('100.000')).toBe('100')
    expect(formatStockQuantity('2.250')).toBe('2.25')
    expect(formatStockQuantity('0.001')).toBe('0.001')
  })

  it('rend un tiret plutôt qu’un zéro faux quand la valeur manque', () => {
    expect(formatStockQuantity(null)).toBe('—')
    expect(formatStockQuantity('')).toBe('—')
    expect(formatStockQuantity('abc')).toBe('—')
  })

  it('additionne des quantités venues en chaînes', () => {
    expect(sumQuantities(['100.000', '20.500', '0.500'])).toBe(121)
    expect(sumQuantities([])).toBe(0)
  })

  /**
   * `RecalculateStockBalance` applique `available = quantity - reserved`. Le
   * frontend ne recalcule rien — il affiche ce que le serveur a écrit — mais la
   * cohérence des trois chiffres reste ce que l'écran donne à lire.
   */
  it('affiche les trois quantités de la formule du solde', () => {
    const balance = { quantity: '100.000', reservedQuantity: '20.000', availableQuantity: '80.000' }

    expect(formatStockQuantity(balance.quantity)).toBe('100')
    expect(formatStockQuantity(balance.reservedQuantity)).toBe('20')
    expect(formatStockQuantity(balance.availableQuantity)).toBe('80')
    expect(Number(balance.quantity) - Number(balance.reservedQuantity)).toBe(
      Number(balance.availableQuantity),
    )
  })
})

describe('sens d’un mouvement', () => {
  /** Le sens n'est pas stocké : il se déduit des deux extrémités. */
  it('déduit le sens des emplacements, jamais du type', () => {
    expect(
      movementDirection({ sourceLocationId: null, destinationLocationId: 'B' }),
    ).toBe('entry')
    expect(
      movementDirection({ sourceLocationId: 'A', destinationLocationId: null }),
    ).toBe('exit')
    expect(
      movementDirection({ sourceLocationId: 'A', destinationLocationId: 'B' }),
    ).toBe('transfer')
  })
})

describe('état d’une réservation', () => {
  /** `releasedAt` est la seule marque : le statut est une chaîne libre. */
  it('reconnaît une réservation libérée à sa date, pas à son statut', () => {
    expect(isReleased({ releasedAt: null })).toBe(false)
    expect(isReleased({ releasedAt: '2026-08-21T08:00:00+00:00' })).toBe(true)
  })
})
