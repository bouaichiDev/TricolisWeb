import type { ImportTargetGroup } from './importTargetFields'

/**
 * Le client final d'une prestation, décrit en entier par le fichier.
 *
 * Deux parties ne se nomment pas de la même façon :
 *
 * - le **donneur d'ordre** est connu de Tricolis : `services[].addressCode`
 *   désigne l'un de ses points, et tout le reste se lit en base ;
 * - le **client final** ne l'est pas : le fichier doit porter son identité, de
 *   quoi le joindre et son adresse. L'import crée l'adresse et le contact, puis
 *   demande ses coordonnées au service GPS.
 *
 * Une prestation va chez l'un **ou** chez l'autre : les deux renseignés, le
 * fichier est refusé. Relevé sur `ImportRecipientRules::RULES`.
 */
export const IMPORT_RECIPIENT_GROUP: ImportTargetGroup = {
  key: 'recipient',
  fields: [
    { path: 'services[].recipient.firstName', ruleKey: 'required', constraint: 'max 255' },
    { path: 'services[].recipient.lastName', ruleKey: 'required', constraint: 'max 255' },
    { path: 'services[].recipient.email', ruleKey: 'required', constraint: 'courriel' },
    { path: 'services[].recipient.phone', ruleKey: 'required', constraint: 'max 255' },
    { path: 'services[].recipient.mobile', ruleKey: 'optional', constraint: 'max 255' },
    {
      path: 'services[].recipient.company',
      ruleKey: 'optional',
      constraint: 'nom de l’adresse ; sinon prénom et nom',
    },
    { path: 'services[].recipient.addressLine1', ruleKey: 'required', constraint: 'rue et numéro' },
    { path: 'services[].recipient.addressLine2', ruleKey: 'optional', constraint: 'max 255' },
    { path: 'services[].recipient.postalCode', ruleKey: 'required', constraint: 'max 64' },
    { path: 'services[].recipient.city', ruleKey: 'required', constraint: 'max 255' },
    { path: 'services[].recipient.country', ruleKey: 'required', constraint: '2 lettres : CH, FR…' },
    { path: 'services[].recipient.instructions', ruleKey: 'optional', constraint: 'consigne d’accès' },
  ],
}
