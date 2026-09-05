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
 * Les identifiants — `customerId`, `agencyId`, `serviceId`, `addressId` — sont
 * volontairement absents : un fichier client porte des références métier, pas
 * les ULID de notre base. `ImportReferenceResolver` les résout à l'import.
 *
 * **Ce qui les remplace est documenté**, et c'est la section « Destination » :
 *
 * - `services[].serviceCode` — le code d'une prestation de l'organisation ;
 * - `services[].addressCode` — le code d'une adresse **du client**, pour un
 *   point récurrent qu'il a enregistré ;
 * - `services[].address.*` — l'adresse **elle-même**, quand le destinataire
 *   change à chaque commande. L'import la crée pour cette prestation, sans la
 *   verser au carnet d'adresses du donneur d'ordre : elle appartient à la
 *   commande qui y va.
 *
 * Le code décide quand il est renseigné, l'adresse prend le relais sinon — si
 * bien qu'une même correspondance sert les deux cas, ligne par ligne.
 *
 * **Les rattachements, eux, sont documentés**, parce qu'ils se font par des
 * clés locales au fichier et non par des identifiants :
 *
 * - `packages[].lines[].lineKey` désigne une ligne par son **rang** dans
 *   `lines`, à partir de 0 — `CreateOrderLines` les indexe ainsi ;
 * - `services[].packages[].packageKey` désigne un colis par le
 *   `packages[].key` que le fichier lui a donné ;
 * - `packages[].parentKey` emboîte un colis dans un autre, qui doit être
 *   déclaré avant lui.
 *
 * Le lien service–colis passe par une table pivot : **un même colis est traité
 * par plusieurs services** — livraison puis montage — chacun avec sa propre
 * quantité et sa propre consigne.
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
          { path: 'packages[].reference', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'packages[].barcode', ruleKey: 'optional', constraint: 'max 128' },
          { path: 'packages[].description', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'packages[].quantity', ruleKey: 'optional', constraint: '> 0' },
          { path: 'packages[].weight', ruleKey: 'optional', constraint: '≥ 0' },
          { path: 'packages[].volume', ruleKey: 'optional', constraint: '≥ 0' },
        ],
      },
      {
        // Les rattachements, qui font tenir la commande ensemble. Ils se font
        // par des **clés locales au fichier**, jamais par des identifiants de
        // notre base : le fichier d'un client ne les connaît pas.
        key: 'links',
        fields: [
          {
            path: 'packages[].key',
            ruleKey: 'optional',
            constraint: 'nom que vous donnez au colis dans le fichier, max 64',
          },
          {
            path: 'packages[].parentKey',
            ruleKey: 'optional',
            constraint: 'colis contenant — à déclarer avant son enfant',
          },
          {
            path: 'packages[].lines[].lineKey',
            ruleKey: 'optional',
            constraint: 'rang de la ligne dans lines, à partir de 0',
          },
          {
            path: 'packages[].lines[].quantity',
            ruleKey: 'optional',
            constraint: 'quantité de la ligne rangée dans ce colis, > 0',
          },
          {
            path: 'services[].packages[].packageKey',
            ruleKey: 'required',
            constraint: 'packages[].key du colis traité par ce service',
          },
          {
            path: 'services[].packages[].quantity',
            ruleKey: 'optional',
            constraint: '> 0',
          },
          {
            path: 'services[].packages[].handlingInstructions',
            ruleKey: 'optional',
            constraint: 'consigne propre à ce service',
          },
        ],
      },
      {
        // Ce que le fichier porte à la place des ULID. Sans cette section,
        // l'écran documentait tout sauf les deux champs sans lesquels aucun
        // import n'aboutit — et personne ne pouvait deviner qu'ils existaient.
        key: 'destination',
        fields: [
          {
            path: 'services[].serviceCode',
            ruleKey: 'required',
            constraint: 'code d’une prestation de l’organisation',
          },
          {
            path: 'services[].addressCode',
            ruleKey: 'conditional',
            constraint: 'code d’une adresse du client ; sinon services[].address',
          },
          {
            path: 'services[].address.addressLine1',
            ruleKey: 'conditional',
            constraint: 'obligatoire sans addressCode — l’adresse est créée',
          },
          { path: 'services[].address.name', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'services[].address.addressLine2', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'services[].address.postalCode', ruleKey: 'optional', constraint: 'max 64' },
          { path: 'services[].address.city', ruleKey: 'optional', constraint: 'max 255' },
          { path: 'services[].address.country', ruleKey: 'optional', constraint: '2 lettres' },
          { path: 'services[].address.instructions', ruleKey: 'optional' },
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
        key: 'contacts',
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
      {
        // Une réclamation se rattache à ce qu'elle conteste. Les trois champs
        // attendent des **identifiants de notre base**, que le fichier d'un
        // client ne porte pas : c'est au futur moteur de les résoudre depuis
        // une référence métier.
        key: 'claimLinks',
        fields: [
          { path: 'orderId', ruleKey: 'optional', constraint: 'identifiant Tricolis, à résoudre' },
          {
            path: 'orderServiceId',
            ruleKey: 'optional',
            constraint: 'identifiant Tricolis, à résoudre',
          },
          { path: 'tourId', ruleKey: 'optional', constraint: 'identifiant Tricolis, à résoudre' },
        ],
      },
    ],
  },
]
