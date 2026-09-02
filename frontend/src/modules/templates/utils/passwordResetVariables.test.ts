import { describe, expect, it } from 'vitest'

import { INVOICE_PATHS } from './invoiceVariables'
import { ORDER_VARIABLES } from './orderVariables'
import { PASSWORD_RESET_VARIABLES } from './passwordResetVariables'

/**
 * Les listes sont recopiées du serveur : un nom qui diverge fait échouer le
 * rendu à l'envoi, quand il est trop tard pour le corriger.
 */
describe('les variables proposées à l’édition', () => {
  /** Sans elle, le courriel n'ouvre rien : c'est la seule indispensable. */
  it('propose le lien de réinitialisation', () => {
    expect(PASSWORD_RESET_VARIABLES).toContain('resetUrl')
  })

  /**
   * Un modèle de réinitialisation ne parle d'aucune commande. Lui proposer
   * `order_number` mènerait à un rendu en échec, et c'est ce que l'écran
   * faisait avant.
   */
  it('ne mélange pas les variables d’une commande', () => {
    const shared = PASSWORD_RESET_VARIABLES.filter((name) =>
      (ORDER_VARIABLES as readonly string[]).includes(name),
    )

    expect(shared).toEqual([])
  })

  /**
   * `organization.name` appartient aux deux, et c'est normal : une facture
   * comme un courriel nomment le transporteur. Ce qui ne doit pas fuiter, ce
   * sont les chemins propres à la facture.
   */
  it('n’emprunte aucun chemin propre à la facture', () => {
    expect(PASSWORD_RESET_VARIABLES.filter((name) => name.startsWith('invoice.'))).toEqual([])
    expect(INVOICE_PATHS).toContain('organization.name')
  })

  /** Six exactement, celles que `SendPasswordResetLink` fournit. */
  it('reprend la liste du serveur', () => {
    expect([...PASSWORD_RESET_VARIABLES].sort()).toEqual([
      'expiresInMinutes',
      'organization.name',
      'resetUrl',
      'user.email',
      'user.firstName',
      'user.lastName',
    ])
  })
})
