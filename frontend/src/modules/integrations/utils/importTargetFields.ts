/**
 * Champs que Tricolis accepte à l'arrivée d'une commande.
 *
 * Cette liste est relevée **une par une** sur `StoreOrderRequest` : ce sont les
 * clés que l'API valide réellement, avec leur obligation et leur contrainte.
 * Rien n'y est inventé, et rien n'y est ajouté « pour faire complet ».
 *
 * Elle documente le **côté gauche** d'un mapping d'import — la destination.
 * Le côté droit décrit le fichier du client, et il n'appartient qu'à lui :
 * `mapping` est validé comme un tableau sans schéma, et le §11 interdit
 * d'inventer un langage de correspondance que le backend ne possède pas.
 *
 * **Aucun moteur ne lit encore ce mapping** : il n'existe ni table `Import`, ni
 * route de déclenchement. Cette référence sert donc à préparer une intégration
 * et à la documenter, pas à la faire fonctionner.
 *
 * Les identifiants — `customerId`, `agencyId`, `catalogItemId` — sont
 * volontairement absents : un fichier client porte des références métier, pas
 * les ULID de notre base. Ils seront résolus par le futur moteur, pas fournis
 * par le fichier.
 */
export interface ImportTargetField {
  /** Le nom exact attendu par l'API. */
  path: string
  /** Ce que `StoreOrderRequest` en exige, en clair. */
  ruleKey: 'required' | 'optional' | 'conditional'
  /** Contrainte utile à connaître avant de mapper, quand il y en a une. */
  constraint?: string
}

export interface ImportTargetGroup {
  key: 'order' | 'lines' | 'packages'
  fields: ImportTargetField[]
}

/** Relevé sur `StoreOrderRequest::rules()`. */
export const IMPORT_TARGET_GROUPS: ImportTargetGroup[] = [
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
      { path: 'packages[].reference', ruleKey: 'optional', constraint: 'max 255' },
      { path: 'packages[].barcode', ruleKey: 'optional', constraint: 'max 128' },
      { path: 'packages[].description', ruleKey: 'optional', constraint: 'max 255' },
      { path: 'packages[].quantity', ruleKey: 'optional', constraint: '> 0' },
      { path: 'packages[].weight', ruleKey: 'optional', constraint: '≥ 0' },
      { path: 'packages[].volume', ruleKey: 'optional', constraint: '≥ 0' },
    ],
  },
]
