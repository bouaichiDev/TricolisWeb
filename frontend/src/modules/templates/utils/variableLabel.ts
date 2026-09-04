import type { TFunction } from 'i18next'

/**
 * Libellé lisible d'une variable, ou son nom quand aucun ne la décrit.
 *
 * `order_number` ne dit rien à qui remplit le formulaire ; « N° commande » si.
 * Le nom technique reste montré à côté — c'est lui qu'on écrit dans le corps du
 * modèle, et le cacher obligerait à le deviner.
 *
 * Une variable saisie à la main n'a pas de libellé, et n'en invente pas : elle
 * s'affiche telle qu'écrite. C'est le cas d'un message composé hors du contexte
 * d'une commande, dont l'auteur fournit lui-même les valeurs à l'envoi.
 *
 * Les clés i18n suivent le chemin — `invoice.lines.description` se lit dans
 * `templateVariables.invoice.lines.description`. Les points y sont déjà des
 * séparateurs de niveau, ce qui évite un second vocabulaire.
 *
 * `_self` en est la seule entorse : `invoice.lines` est à la fois une valeur —
 * la section à répéter — et le parent de ses champs. La clé résoudrait un
 * objet, qu'i18next ne sait pas rendre ; le repli va donc chercher le libellé
 * un niveau plus bas.
 */
export function variableLabel(t: TFunction, name: string): string {
  const own = t(`templateVariables.${name}`, { defaultValue: '' })

  if (typeof own === 'string' && own !== '') return own

  return t(`templateVariables.${name}._self`, { defaultValue: name })
}

/** Vrai quand un libellé existe : sinon, l'afficher deux fois n'apprend rien. */
export function hasVariableLabel(t: TFunction, name: string): boolean {
  return variableLabel(t, name) !== name
}
