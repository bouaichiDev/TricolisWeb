import { describe, expect, it } from 'vitest'

import { ApiError } from '@/shared/api/errors'

import { emptyDraft, emptyPackage, emptyService } from './orderDraft'
import { fieldError, issuesOf, mapOrderErrors } from './orderErrors'
import { serializeOrderWithKeys } from './serializeOrder'

function serialized() {
  const draft = emptyDraft()
  draft.customerId = '01JQZ000000000000000CUST'
  draft.agencyId = '01JQZ0000000000000000AGY1'

  const parent = emptyPackage()
  const child = { ...emptyPackage(), parentKey: parent.key }
  // Saisis à l'envers : la sérialisation les réordonne, et c'est justement ce
  // décalage que le mapper doit savoir remonter.
  draft.packages = [child, parent]
  draft.services = [emptyService(1), emptyService(2)]

  return { draft, keys: serializeOrderWithKeys(draft) }
}

const validation = (errors: Record<string, string[]>) =>
  new ApiError(422, 'Les données fournies sont invalides.', errors)

describe('mapOrderErrors', () => {
  it('rattache une erreur d’en-tête à l’étape Général', () => {
    const { keys } = serialized()
    const report = mapOrderErrors(validation({ customerId: ['Client inconnu.'] }), keys)

    expect(report.stepsInError).toEqual(['general'])
    expect(report.issues[0].field).toBe('customerId')
  })

  it('rattache une erreur de ligne à la clé stable de la ligne', () => {
    const { draft, keys } = serialized()
    const report = mapOrderErrors(validation({ 'lines.0.quantity': ['Trop petit.'] }), keys)

    expect(report.stepsInError).toEqual(['lines'])
    expect(report.issues[0].entityKey).toBe(draft.lines[0].key)
    expect(report.issues[0].field).toBe('quantity')
  })

  /** Le §34 le nomme explicitement : `services.0.contacts.0.email`. */
  it('rattache une erreur de contact au service et au bon contact', () => {
    const { draft, keys } = serialized()
    const report = mapOrderErrors(
      validation({ 'services.0.contacts.0.email': ['Adresse invalide.'] }),
      keys,
    )

    expect(report.stepsInError).toEqual(['services'])

    const issues = issuesOf(report, draft.services[0].key, { kind: 'contacts', index: 0 })

    expect(fieldError(issues, 'email')).toBe('Adresse invalide.')
  })

  it('rattache une erreur d’affectation au bon colis malgré le réordonnancement', () => {
    const { draft, keys } = serialized()
    // Position 0 dans l'envoi = le parent, saisi en second dans le formulaire.
    const report = mapOrderErrors(
      validation({ 'packages.0.lines.1.quantity': ['Dépassement.'] }),
      keys,
    )

    expect(report.stepsInError).toEqual(['packages'])
    expect(report.issues[0].entityKey).toBe(draft.packages[1].key)
    expect(report.issues[0].sub).toEqual({ kind: 'lines', index: 1 })
  })

  it('vise le second service quand le chemin le désigne', () => {
    const { draft, keys } = serialized()
    const report = mapOrderErrors(validation({ 'services.1.sequence': ['Déjà utilisée.'] }), keys)

    expect(report.issues[0].entityKey).toBe(draft.services[1].key)
  })

  it('signale plusieurs étapes à la fois, dans l’ordre du parcours', () => {
    const { keys } = serialized()
    const report = mapOrderErrors(
      validation({
        'services.0.addressId': ['Introuvable.'],
        customerId: ['Inconnu.'],
        'lines.0.name': ['Obligatoire.'],
      }),
      keys,
    )

    expect(report.stepsInError).toEqual(['general', 'lines', 'services'])
  })

  it('conserve un chemin inconnu plutôt que de le jeter', () => {
    const { keys } = serialized()
    const report = mapOrderErrors(validation({ 'lines.9.quantity': ['Hors bornes.'] }), keys)

    expect(report.issues).toHaveLength(1)
    expect(report.issues[0].entityKey).toBeNull()
  })

  /** Un refus métier n'a pas de champ : son message est affiché tel quel. */
  it('remonte un 409 en message global, sans étape en erreur', () => {
    const { keys } = serialized()
    const report = mapOrderErrors(
      new ApiError(409, 'Cette commande n’est plus modifiable.'),
      keys,
    )

    expect(report.stepsInError).toEqual([])
    expect(report.message).toBe('Cette commande n’est plus modifiable.')
  })

  it('remonte un 422 sans chemin exploitable en message global', () => {
    const { keys } = serialized()
    const report = mapOrderErrors(validation({}), keys)

    expect(report.message).toBe('Les données fournies sont invalides.')
  })
})
