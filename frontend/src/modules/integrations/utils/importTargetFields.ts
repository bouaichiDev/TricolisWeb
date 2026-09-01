/**
 * Champs que Tricolis accepte à l'arrivée d'un document client.
 *
 * Ces listes sont relevées **une par une** sur les Form Requests réelles —
 * `StoreOrderRequest` et `StoreClaimRequest` — avec leur obligation et leur
 * contrainte. Rien n'y est inventé, et rien n'y est ajouté « pour faire
 * complet ».
 *
 * Elles documentent le **côté gauche** d'un mapping d'import : la destination.
 * Le côté droit décrit le fichier du client et n'appartient qu'à lui — `mapping`
 * est validé comme un tableau sans schéma, et le §11 interdit d'inventer un
 * langage de correspondance que le backend ne possède pas.
 *
 * **Aucun moteur ne lit encore ce mapping** : il n'existe ni table `Import`, ni
 * route de déclenchement. Cette référence sert à préparer et documenter une
 * intégration, pas à la faire fonctionner.
 *
 * Les identifiants — `customerId`, `agencyId`, `serviceId`, `addressId` — sont
 * volontairement absents : un fichier client porte des références métier, pas
 * les ULID de notre base. Ils devront être résolus par le futur moteur, et ce
 * point est le principal travail de conception qui reste.
 */
export interface ImportTargetField {
  /** Le nom exact attendu par l'API. */
  path: string
  /** Ce que la Form Request en exige, en clair. */
  ruleKey: 'required' | 'optional' | 'conditional'
  /** Contrainte utile à connaître avant de mapper, quand il y en a une. */
  constraint?: string
}

export interface ImportTargetGroup {
  key: string
  fields: ImportTargetField[]
}

/**
 * Une cible d'import : un document entier, avec ses sections.
 *
 * Commande et réclamation sont **deux documents distincts**, servis par deux
 * endpoints différents. Les mélanger dans une seule liste laisserait croire
 * qu'un même fichier porte des colis et un type de réclamation.
 */
export interface ImportTarget {
  key: 'order' | 'claim'
  groups: ImportTargetGroup[]
}

/** Relevé sur `StoreOrderRequest::rules()` et `StoreClaimRequest::rules()`. */
export const IMPORT_TARGETS: ImportTarget[] = [
  {
    key: 'order',
    groups: [
      {
        key: 'order',
        fields: [
          { path: 'orderDate', ruleKey: 'required', constraint: 'date' },
          { path: 'externalReference', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'customerReference', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'orderType', ruleKey: 'optional', constraint: 'max 64' },
          { path: 'groupCode', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'currencyCode', ruleKey: 'optional', constraint: '3 lettres majuscules' },
          { path: 'internalRemark', ruleKey: 'optional' },
          { path: 'workerRemark', ruleKey: 'optional' },
        ],
      },
      {
        key: 'lines',
        fields: [
          { path: 'lines[].quantity', ruleKey: 'required', constraint: '> 0' },
          {
            path: 'lines[].name',
            ruleKey: 'conditional',
            constraint: 'obligatoire sans article de catalogue',
          },
          { path: 'lines[].articleCode', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'lines[].barcode', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'lines[].externalReference', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'lines[].description', ruleKey: 'optional' },
          { path: 'lines[].weight', ruleKey: 'optional', constraint: '≥ 0' },
          { path: 'lines[].volume', ruleKey: 'optional', constraint: '≥ 0' },
          { path: 'lines[].length', ruleKey: 'optional', constraint: '≥ 0' },
          { path: 'lines[].width', ruleKey: 'optional', constraint: '≥ 0' },
          { path: 'lines[].height', ruleKey: 'optional', constraint: '≥ 0' },
          { path: 'lines[].purchasePrice', ruleKey: 'optional', constraint: '≥ 0' },
          { path: 'lines[].sellingPrice', ruleKey: 'optional', constraint: '≥ 0' },
        ],
      },
      {
        key: 'packages',
        fields: [
          { path: 'packages[].key', ruleKey: 'optional', constraint: 'référence interne au fichier' },
          { path: 'packages[].parentKey', ruleKey: 'optional', constraint: 'colis parent' },
          { path: 'packages[].reference', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'packages[].barcode', ruleKey: 'optional', constraint: 'max 128' },
          { path: 'packages[].description', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'packages[].quantity', ruleKey: 'optional', constraint: '> 0' },
          { path: 'packages[].weight', ruleKey: 'optional', constraint: '≥ 0' },
          { path: 'packages[].volume', ruleKey: 'optional', constraint: '≥ 0' },
          { path: 'packages[].lines[].lineKey', ruleKey: 'optional', constraint: 'ligne rangée dedans' },
          { path: 'packages[].lines[].quantity', ruleKey: 'optional', constraint: '> 0' },
        ],
      },
      {
        // Une commande sans service n'est pas transportable : `services` est
        // `required|min:1`, et presque tous ses champs le sont aussi. C'est la
        // section la plus exigeante d'un import de commande.
        key: 'services',
        fields: [
          { path: 'services[].serviceNumber', ruleKey: 'required', constraint: 'max 255' },
          { path: 'services[].sequence', ruleKey: 'required', constraint: 'entier ≥ 1' },
          { path: 'services[].requestedDate', ruleKey: 'required', constraint: 'date' },
          { path: 'services[].quantity', ruleKey: 'required', constraint: '> 0' },
          { path: 'services[].unit', ruleKey: 'required', constraint: 'max 32' },
          { path: 'services[].requiredTimeMinutes', ruleKey: 'required', constraint: 'entier ≥ 0' },
          { path: 'services[].remainingTimeMinutes', ruleKey: 'required', constraint: 'entier ≥ 0' },
          { path: 'services[].weight', ruleKey: 'required', constraint: '≥ 0' },
          { path: 'services[].volume', ruleKey: 'required', constraint: '≥ 0' },
          { path: 'services[].packageCount', ruleKey: 'required', constraint: 'entier ≥ 0' },
          { path: 'services[].customerUnitPrice', ruleKey: 'required', constraint: '≥ 0' },
          { path: 'services[].customerTotalPrice', ruleKey: 'required', constraint: '≥ 0' },
          { path: 'services[].providerUnitCost', ruleKey: 'required', constraint: '≥ 0' },
          { path: 'services[].providerTotalCost', ruleKey: 'required', constraint: '≥ 0' },
          {
            path: 'services[].status',
            ruleKey: 'required',
            constraint: 'draft, pending, ready_to_plan, planned, in_progress, completed, failed, cancelled, invoiced',
          },
          { path: 'services[].requestedFrom', ruleKey: 'optional', constraint: 'date' },
          { path: 'services[].requestedTo', ruleKey: 'optional', constraint: 'date ≥ requestedFrom' },
          { path: 'services[].instructions', ruleKey: 'optional' },
        ],
      },
      {
        key: 'serviceContacts',
        fields: [
          {
            path: 'services[].contacts[].firstName',
            ruleKey: 'conditional',
            constraint: 'obligatoire sans contact existant',
          },
          { path: 'services[].contacts[].lastName', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'services[].contacts[].phone', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'services[].contacts[].mobile', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'services[].contacts[].email', ruleKey: 'optional', constraint: 'courriel' },
          {
            path: 'services[].contacts[].contactRole',
            ruleKey: 'optional',
            constraint: 'load, delivery, billing, operations, emergency, other',
          },
          { path: 'services[].contacts[].isPrimary', ruleKey: 'optional', constraint: 'booléen' },
          {
            path: 'services[].packages[].packageKey',
            ruleKey: 'optional',
            constraint: 'colis rattaché, max 64',
          },
          { path: 'services[].packages[].quantity', ruleKey: 'optional', constraint: '> 0' },
          { path: 'services[].packages[].handlingInstructions', ruleKey: 'optional' },
        ],
      },
    ],
  },
  {
    key: 'claim',
    groups: [
      {
        key: 'claim',
        fields: [
          { path: 'title', ruleKey: 'required', constraint: 'max 255' },
          { path: 'claimType', ruleKey: 'required', constraint: 'max 64, chaîne libre' },
          { path: 'status', ruleKey: 'required', constraint: 'code du référentiel, max 32' },
          { path: 'description', ruleKey: 'optional' },
          { path: 'cause', ruleKey: 'optional', constraint: 'max 255' },
        ],
      },
    ],
  },
]
